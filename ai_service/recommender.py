"""Upohar gift recommender (Python, scikit-learn).

How it works
  1. TRAIN   An intent model learns to read a sentence ("birthday gift for my mom, she loves books, under 3000 taka") and
             predict the gift tags it means: gender, age group, occasion, hobby, profession, "gift for". It is a multi-label
             logistic-regression classifier on word + character n-gram TF-IDF, trained on thousands of example sentences
             generated from your own filter options (so options the admin adds later are learnt too).
  2. INDEX   Every approved product becomes a TF-IDF document (name + category + description + tag labels).
  3. RANK    score = how well the product's tags match the predicted tags (specific products beat "suits everyone" ones)
                     + how well its text matches the keywords (perfume, watch, book...) + budget fit.
             The final picks are chosen with a diversity step so you do not get three near-identical gifts.
"""
import difflib
import os
import re
import threading
import time
from datetime import datetime

import joblib
import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import f1_score, precision_score, recall_score
from sklearn.model_selection import train_test_split
from sklearn.multiclass import OneVsRestClassifier
from sklearn.pipeline import FeatureUnion

import db
import nlp

DIMS = ["gender", "age", "occasion", "hobby", "profession", "relation"]
THR = 0.40          # a tag is "understood" when the model is at least this sure
BDT_PER_USD = float(db.setting("BDT_PER_USD", 120))
MODEL_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "model", "intent_model.joblib")

BUDGETS = [("under50", 50), ("50-150", 150), ("150-300", 300), ("300plus", 10 ** 9)]
CHEAPER = re.compile(r"\b(cheaper|cheap|less expensive|lower (?:the )?(?:price|budget)|kom dam|kom dami|affordable|budget friendly|too expensive|too costly)\b")
PREMIUM = re.compile(r"\b(more expensive|premium|luxury|luxurious|better quality|high end|beshi dam|beshi dami|something nicer|fancier)\b")
MORE = re.compile(r"\b(more|another|other|different|else|again|next|new ideas|aro|ar ekta|onno)\b")
GREETING = re.compile(r"^\W*(hi+|hello+|hey+|hola|salam|assalamu\s*alaikum|assalamualaikum|good (?:morning|evening|afternoon)|namaste|how are you|thanks|thank you)\W*$")


def _money(usd):
    return f"${usd:,.2f} · ≈ ৳{usd * BDT_PER_USD:,.0f}"


class Recommender:
    def __init__(self):
        self.lock = threading.RLock()
        self.ready = False
        self.products, self.tags, self.options = [], {}, {}
        self.intent = None                     # dict(vec, clf, classes, ...)
        self.meta = {}
        self.fp = None
        self._last_check = 0.0
        self.started = time.time()

    # ------------------------------------------------------------------ startup / training
    def ensure_ready(self):
        with self.lock:
            self._load_catalog()
            if not self._load_model():
                self.train()
            self._build_index()
            self.ready = True

    def _load_catalog(self):
        self.products, self.tags, self.options = db.load_catalog()
        self.options = {d: self.options.get(d, {}) for d in DIMS}
        self.fp = db.fingerprint()
        self.pid_index = {p["id"]: i for i, p in enumerate(self.products)}

    def _load_model(self):
        try:
            blob = joblib.load(MODEL_FILE)
        except Exception:
            return False
        if blob.get("options_fp") != self.fp[1]:
            return False                       # filter options changed since training
        self.intent, self.meta = blob, blob["meta"]
        return True

    def train(self, n_queries=9000):
        """Train the intent model from the current filter options. Takes a few seconds."""
        with self.lock:
            t0 = time.time()
            options = {d: self.options[d] for d in DIMS if self.options.get(d)}
            if not options:
                raise RuntimeError("No filter options found. Import database/upohar_admin.sql first.")
            X, Y = nlp.make_training_set(options, n=n_queries)
            classes = sorted({l for ls in Y for l in ls})
            idx = {c: i for i, c in enumerate(classes)}
            M = np.zeros((len(X), len(classes)), dtype=np.int8)
            for r, ls in enumerate(Y):
                for l in ls:
                    M[r, idx[l]] = 1
            Xtr, Xte, Ytr, Yte = train_test_split(X, M, test_size=0.15, random_state=3)

            def fit(xs, ys):
                vec = FeatureUnion([
                    ("char", TfidfVectorizer(analyzer="char_wb", ngram_range=(2, 5), sublinear_tf=True, min_df=2)),
                    ("word", TfidfVectorizer(ngram_range=(1, 2), sublinear_tf=True)),
                ])
                A = vec.fit_transform(xs)
                clf = OneVsRestClassifier(LogisticRegression(C=12, solver="liblinear", max_iter=300))
                clf.fit(A, ys)
                return vec, clf

            vec, clf = fit(Xtr, Ytr)                              # evaluate on unseen sentences
            pred = (clf.predict_proba(vec.transform(Xte)) >= 0.5).astype(int)
            f1 = f1_score(Yte, pred, average="micro", zero_division=0)
            prec = precision_score(Yte, pred, average="micro", zero_division=0)
            rec = recall_score(Yte, pred, average="micro", zero_division=0)
            vec, clf = fit(X, M)                                  # final model uses all data

            self.meta = {
                "trained_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"), "train_seconds": round(time.time() - t0, 1),
                "queries": len(X), "classes": len(classes), "f1": round(float(f1), 4), "precision": round(float(prec), 4), "recall": round(float(rec), 4),
            }
            self.intent = {"vec": vec, "clf": clf, "classes": classes, "options_fp": self.fp[1], "meta": self.meta}
            os.makedirs(os.path.dirname(MODEL_FILE), exist_ok=True)
            joblib.dump(self.intent, MODEL_FILE)
            return self.meta

    def retrain(self):
        """Reload the catalog and train again (used by the admin 'Retrain' button)."""
        with self.lock:
            self._load_catalog()
            meta = self.train()
            self._build_index()
            self.ready = True
            return meta

    def _build_index(self):
        docs = []
        for p in self.products:
            labels = []
            for d in DIMS:
                labels += [self.options[d].get(s, s) for s in self.tags.get(p["id"], {}).get(d, ())]
            docs.append(f"{p['name']} {p['name']} {p['category_label']} {p['description']} {' '.join(labels)}".lower())

        # learned from the catalog: how often does each category carry each tag? (gadgets -> tech, books -> reading ...)
        self.cat_aff, counts = {}, {}
        for p in self.products:
            c = p["category"]
            counts[c] = counts.get(c, 0) + 1
            for d, slugs in self.tags.get(p["id"], {}).items():
                for s in slugs:
                    self.cat_aff.setdefault(c, {}).setdefault(d, {})
                    self.cat_aff[c][d][s] = self.cat_aff[c][d].get(s, 0) + 1
        for c, dd in self.cat_aff.items():
            for d, ss in dd.items():
                for s in ss:
                    ss[s] /= counts[c]
        # words we recognise (intent vocabulary + product words); anything else close to one of them is treated as a typo
        self.vocab = set(self.intent["vec"].transformer_list[1][1].vocabulary_) if self.intent else set()
        self.known = self.vocab | {w for d in docs for w in re.findall(r"[a-z']+", d)} | nlp.STOP
        self.tvec = TfidfVectorizer(analyzer="char_wb", ngram_range=(3, 5), sublinear_tf=True)
        self.dmat = self.tvec.fit_transform(docs) if docs else None

    def refresh_if_stale(self):
        """Pick up new / approved / removed products and changed filter options without a restart."""
        if time.time() - self._last_check < 20:
            return
        self._last_check = time.time()
        try:
            fp = db.fingerprint()
        except Exception:
            return
        if fp == self.fp:
            return
        with self.lock:
            options_changed = fp[1] != self.fp[1]
            self._load_catalog()
            if options_changed:
                self.train()
            self._build_index()

    # ------------------------------------------------------------------ understanding a message
    def _probs(self, text):
        """{dim: {slug: probability}} for one message."""
        P = self.intent["clf"].predict_proba(self.intent["vec"].transform([text.lower()]))[0]
        out = {d: {} for d in DIMS}
        for c, p in zip(self.intent["classes"], P):
            d, s = c.split(":", 1)
            if d in out:
                out[d][s] = float(p)
        age = nlp.parse_age(text)                                        # "25 year old" is exact: trust it
        if age and age in out["age"]:
            out["age"] = {s: (0.95 if s == age else 0.02) for s in out["age"]}
        return out

    def _correct(self, text):
        """frend -> friend, doctr -> doctor, wief -> wife. Only touches words that are not known at all."""
        out = []
        for w in re.findall(r"\S+", text):
            core = re.sub(r"[^a-zA-Z']", "", w).lower()
            if len(core) >= 4 and core not in self.known and not any(ch.isdigit() for ch in w):
                m = difflib.get_close_matches(core, self.vocab, n=1, cutoff=0.74)
                if m:
                    w = m[0]
            out.append(w)
        return " ".join(out)

    def _knows_words(self, text):
        vocab = self.intent["vec"].transformer_list[1][1].vocabulary_
        return any(w in vocab for w in re.findall(r"[a-z0-9']+", text.lower()))

    def understand(self, user_texts, shown):
        """Combine the last few user messages. A newer mention of a dimension overrides an older one."""
        msgs = [self._correct(t) for t in user_texts if t.strip()][-4:][::-1]          # newest first
        per_msg = [self._probs(t) for t in msgs]
        probs, conf, source = {}, {}, {}
        for d in DIMS:
            probs[d], conf[d] = {}, 0.0
            for age_rank, pm in enumerate(per_msg):
                c = max(pm[d].values()) if pm[d] else 0.0
                if c >= THR:
                    decay = 0.92 ** age_rank
                    probs[d] = {s: p * decay for s, p in pm[d].items()}
                    conf[d] = c * decay
                    source[d] = age_rank
                    break

        budget = None
        for t in msgs:
            budget = nlp.parse_budget(t, BDT_PER_USD)
            if budget:
                break
        latest = msgs[0].lower() if msgs else ""
        shown_prices = [self.products[self.pid_index[i]]["price"] for i in shown[-3:] if i in self.pid_index]
        if shown_prices and CHEAPER.search(latest):
            budget = {"min": None, "max": min(shown_prices) * 0.75, "label": f"cheaper than ${min(shown_prices):.0f}"}
        elif shown_prices and PREMIUM.search(latest):
            budget = {"min": max(shown_prices) * 1.15, "max": None, "label": f"above ${max(shown_prices):.0f}"}
        wants_cheap = bool(CHEAPER.search(latest)) and not shown_prices
        return probs, conf, budget, wants_cheap, msgs

    # ------------------------------------------------------------------ ranking
    def _text_scores(self, msgs):
        q = " ".join(nlp.clean_for_search(t) for t in msgs[:2]).strip()
        if not q or self.dmat is None:
            return np.zeros(len(self.products))
        sims = (self.dmat @ self.tvec.transform([q]).T).toarray().ravel()
        return np.clip((sims - 0.06) / 0.34, 0, 1)

    def suggest(self, user_texts, shown=None, limit=3):
        if not self.ready:
            self.ensure_ready()
        self.refresh_if_stale()
        shown = [int(i) for i in (shown or [])]
        limit = max(1, min(6, int(limit or 3)))
        latest_raw = next((t for t in reversed(user_texts) if t.strip()), "").lower()
        if GREETING.match(latest_raw):
            return {"reply": "Hello! I'm the Upohar gift assistant. Tell me who the gift is for, the occasion and your budget, for example: "
                             "\"a birthday gift for my mom under 3000 taka\" or \"something for a friend who loves tech\".",
                    "gifts": [], "understood": [], "clarify": True}
        probs, conf, budget, wants_cheap, msgs = self.understand(user_texts, shown)
        if not self._knows_words(msgs[0] if msgs else ""):
            probs, conf = {d: {} for d in DIMS}, {d: 0.0 for d in DIMS}          # gibberish: do not guess
        text_s = self._text_scores(msgs)
        active = {d: c for d, c in conf.items() if c >= THR}
        latest = msgs[0].lower() if msgs else ""
        has_signal = bool(active) or text_s.max() > 0.25 or budget is not None

        understood = []
        best_slug = {}
        for d in DIMS:
            if d in active:
                ranked = sorted(probs[d].items(), key=lambda kv: -kv[1])
                picks = [s for s, p in ranked if p >= THR][:2]
                best_slug[d] = picks
                understood += [self.options[d].get(s, s) for s in picks]
        if budget:
            understood.append(budget["label"] + (f" (≈ ৳{budget['max'] * BDT_PER_USD:,.0f})" if budget.get("bdt") and budget.get("max") else ""))

        if not has_signal:
            return {"reply": "I could not quite catch that. Tell me who the gift is for, the occasion and your budget, "
                             "for example: \"a birthday gift for my mom under 3000 taka\" or \"something for a friend who loves tech\".",
                    "gifts": [], "understood": [], "clarify": True}

        # candidates: budget first, then relax step by step
        pool = [i for i, p in enumerate(self.products) if p["id"] not in shown] or list(range(len(self.products)))
        note = ""
        if budget:
            def ok(i, slack, use_min=True):
                pr = self.products[i]["price"]
                if budget.get("max") is not None and pr > budget["max"] * (1 + slack):
                    return False
                if use_min and budget.get("min") is not None and pr < budget["min"] * (1 - slack):
                    return False
                return True
            for slack, use_min, msg in [(0.05, True, ""), (0.35, True, "Not many gifts fit that budget exactly, so I stretched it a little. "),
                                        (0.35, False, "Not many gifts fit that budget exactly, so I stretched it a little. ")]:
                cand = [i for i in pool if ok(i, slack, use_min)]
                if len(cand) >= limit:
                    note = msg
                    break
            else:
                by_price = sorted(pool, key=lambda i: self.products[i]["price"], reverse=budget.get("max") is None)
                cand = by_price[:25]
                note = ("These are the cheapest matches I have. " if budget.get("max") is not None else "These are the most premium matches I have. ")
            pool = cand

        N = {d: max(1, len(self.options[d])) for d in DIMS}
        w_text = 0.40 if text_s.max() >= 0.6 else 0.22               # a product word like "perfume" / "watch" counts for more
        scored = []
        for i in pool:
            p = self.products[i]
            ptags = self.tags.get(p["id"], {})
            num = den = 0.0
            hits = 0
            veto = 1.0
            for d, c in active.items():
                t = ptags.get(d, ())
                best = max((probs[d].get(s, 0.0) for s in t), default=0.0)
                spec = 1 - 0.20 * (len(t) / N[d])                        # specific gifts edge out "suits everyone" gifts
                if d in ("hobby", "occasion", "profession"):             # does its category usually carry this tag? (gadgets -> tech)
                    ca = self.cat_aff.get(p["category"], {}).get(d, {})
                    tot = sum(probs[d].values()) or 1.0
                    aff = sum(pr * ca.get(s, 0.0) for s, pr in probs[d].items() if pr >= THR) / tot
                    num += c * (0.78 * best * spec + 0.22 * aff)
                else:
                    num += c * best * spec
                den += c
                if best >= THR:
                    hits += 1
                if d in ("gender", "age") and c >= 0.7 and best < 0.2:
                    veto = 0.35                                          # clearly the wrong gender / age group
            tag_score = num / den if den else 0.0
            coverage = hits / len(active) if active else 0.0            # does it match EVERY thing the user asked for?
            if budget and budget.get("max"):
                fit = min(1.0, p["price"] / budget["max"])               # gifts closer to the budget feel more generous
                bonus = 0.5 + 0.5 * fit
            elif wants_cheap:
                bonus = 1 - min(1.0, p["price"] / 150)
            else:
                bonus = 0.5
            prior = 0.03 if p.get("badge") == "hot" else 0.01 if p.get("badge") == "new" else 0.0
            if active:
                score = (0.46 * tag_score + 0.18 * coverage + w_text * text_s[i] + 0.08 * bonus + prior) * veto
            else:
                score = 0.75 * text_s[i] + 0.25 * bonus + prior
            scored.append((score, i))

        # pick with diversity (different categories / shops)
        scored.sort(key=lambda x: -x[0])
        picked, cats, shops, names = [], {}, {}, set()
        remaining = scored[:60]
        while remaining and len(picked) < limit:
            adj = [(s - 0.12 * cats.get(self.products[i]["category"], 0) - 0.06 * shops.get(self.products[i]["seller_id"], 0), s, i) for s, i in remaining]
            adj.sort(key=lambda x: -x[0])
            _, s, i = adj[0]
            remaining = [(a, b) for a, b in remaining if b != i]
            nm = self.products[i]["name"].strip().lower()
            if nm in names:
                continue                                    # never show two gifts with the same name
            names.add(nm)
            picked.append((s, i))
            cats[self.products[i]["category"]] = cats.get(self.products[i]["category"], 0) + 1
            shops[self.products[i]["seller_id"]] = shops.get(self.products[i]["seller_id"], 0) + 1

        gifts = []
        for rank, (s, i) in enumerate(picked):
            p = self.products[i]
            ptags = self.tags.get(p["id"], {})
            matched = []
            for d in active:
                hit = [x for x in best_slug.get(d, []) if x in ptags.get(d, ())]
                matched += [self.options[d].get(x, x) for x in hit]
            first = re.split(r"(?<=[.!?])\s", (p["description"] or "").strip())[0][:90]
            why = (f"Fits: {', '.join(matched[:4])}. " if matched else "") + first
            gifts.append({"id": p["id"], "name": p["name"], "why": why.strip(), "price": _money(p["price"]), "tag": p["category_label"],
                          "image": p["image"] or "", "emoji": p["emoji"], "shop": p["shop_name"], "top": rank == 0,
                          "link": "filter.php?q=" + re.sub(r"\s+", "+", re.sub(r"[^A-Za-z0-9 ]", "", p["name"]))})

        browse = [f"{d}={best_slug[d][0]}" for d in DIMS if best_slug.get(d)]
        if budget and budget.get("max"):
            browse.append("budget=" + next(k for k, hi in BUDGETS if budget["max"] <= hi))
        reply = (note + f"Here {'is' if len(gifts) == 1 else 'are'} {len(gifts)} gift idea{'s' if len(gifts) != 1 else ''} that fit"
                 + (f" what you described ({', '.join(understood)})." if understood else " your message.")
                 + (" Tell me more about the person, the occasion or your budget and I will sharpen them." if len(active) < 2 else
                    " Want something cheaper, fancier or different? Just say so."))
        return {"reply": reply, "gifts": gifts, "understood": understood, "clarify": False,
                "browse_url": "filter.php" + ("?" + "&".join(browse) if browse else "")}

    def status(self):
        return {"ready": self.ready, "products": len(self.products), "tags": sum(len(v) for t in self.tags.values() for v in t.values()),
                "options": {d: len(self.options[d]) for d in DIMS}, "model": self.meta, "uptime_seconds": int(time.time() - self.started),
                "bdt_per_usd": BDT_PER_USD}
