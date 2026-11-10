"""Language side of the gift AI: words -> options, budget / age parsing, and the training-data generator.

The lexicon below is only a SEED. The intent model is trained on thousands of queries generated from it
(and from the filter options in your database), so it also understands typos, new words around the same
idea, and any option the admin adds later (its label is used as a training phrase automatically)."""
import random
import re

# ---------------------------------------------------------------- seed lexicon (English + Banglish)
LEX = {
    "gender": {
        "male": ["man", "male", "boy", "guy", "gentleman", "him", "his", "he", "men's", "boyfriend", "husband", "father", "dad", "brother",
                 "son", "uncle", "grandfather", "chele", "purush", "bhai", "abbu", "baba", "shami", "bhaiya"],
        "female": ["woman", "female", "girl", "lady", "her", "she", "women's", "girlfriend", "wife", "mother", "mom", "mum", "sister",
                   "daughter", "aunt", "grandmother", "meye", "mohila", "apu", "bon", "ammu", "maa", "bou", "stri", "nani", "apa"],
    },
    "age": {
        "kid": ["kid", "child", "little one", "toddler", "baby", "small child", "young child", "shishu", "baccha", "primary school"],
        "teen": ["teen", "teenager", "teenage", "adolescent", "school student", "high school", "college going", "young teen"],
        "20-30": ["young adult", "in my twenties", "twenties", "20s", "fresh graduate", "early career", "young professional"],
        "31-45": ["thirties", "in their 30s", "middle aged", "30s", "40s", "forties", "mid career"],
        "46-60": ["fifties", "50s", "senior professional", "late career", "in their fifties"],
        "60plus": ["elderly", "senior citizen", "old", "grandparent", "retired", "sixties", "seventies", "grandfather", "grandmother", "dadu", "nani"],
    },
    "occasion": {
        "birthday": ["birthday", "bday", "b'day", "jonmodin", "janmodin", "happy birthday", "birth day"],
        "anniversary": ["anniversary", "wedding anniversary", "years together", "couple anniversary"],
        "wedding": ["wedding", "marriage", "biye", "bibaho", "bride", "groom", "newlywed", "shaadi", "wedding present"],
        "eid": ["eid", "eid ul fitr", "eid ul adha", "eid gift", "eidi", "eid mubarak"],
        "graduation": ["graduation", "convocation", "passing exam", "exam result", "degree", "got a new job", "promotion"],
        "housewarming": ["housewarming", "new home", "new house", "moving in", "new flat", "new apartment"],
        "valentine": ["valentine", "valentine's day", "romantic", "date night", "love"],
        "official": ["official", "corporate", "office", "business gift", "client gift", "work", "professional"],
        "thankyou": ["thank you", "thanks", "appreciation", "gratitude", "thank-you"],
        "justbecause": ["just because", "no reason", "random gift", "cheer up"],
    },
    "hobby": {
        "reading": ["reading", "books", "book lover", "novel", "bookworm", "literature", "poetry", "boi pora"],
        "tech": ["tech", "gadgets", "technology", "electronics", "geek", "computers", "programming", "gadget lover"],
        "gardening": ["gardening", "plants", "garden", "flowers", "greenery", "plant lover", "baagan"],
        "gaming": ["gaming", "games", "gamer", "playstation", "video games", "board games", "pc games"],
        "sports": ["sports", "fitness", "gym", "workout", "football", "cricket", "running", "exercise", "athlete", "yoga", "khela"],
        "music": ["music", "songs", "guitar", "singing", "headphones", "musician", "gaan", "piano"],
        "travel": ["travel", "travelling", "trips", "traveler", "adventure", "hiking", "touring", "ghora"],
        "cooking": ["cooking", "kitchen", "chef", "baking", "foodie", "food lover", "cook", "ranna"],
        "art": ["art", "painting", "drawing", "craft", "creative", "sketching", "artist", "handmade"],
        "fashion": ["fashion", "style", "clothes", "trendy", "dressing up", "jewellery", "accessories", "stylish"],
        "wellness": ["wellness", "self care", "spa", "relaxing", "meditation", "skincare", "calm", "relax"],
    },
    "profession": {
        "student": ["student", "studying", "university", "varsity", "school", "college", "chatro", "chatri"],
        "engineer": ["engineer", "engineering", "software engineer", "developer", "programmer", "it professional"],
        "doctor": ["doctor", "physician", "medical", "nurse", "surgeon", "medical student", "daktar"],
        "teacher": ["teacher", "professor", "lecturer", "tutor", "educator", "shikkhok"],
        "business": ["businessman", "business", "entrepreneur", "boss", "manager", "ceo", "executive", "shopkeeper", "banker"],
        "designer": ["designer", "graphic designer", "ui designer", "architect", "creative professional"],
        "homemaker": ["homemaker", "housewife", "stay at home", "home maker", "grihini"],
        "other": ["freelancer", "worker", "driver", "farmer", "other profession"],
    },
    "relation": {
        "partner": ["partner", "girlfriend", "boyfriend", "wife", "husband", "fiance", "fiancee", "spouse", "bou", "shami", "better half"],
        "friend": ["friend", "bestie", "best friend", "buddy", "bondhu", "classmate", "roommate", "pal"],
        "parent": ["parent", "mother", "father", "mom", "mum", "dad", "baba", "abbu", "ammu", "maa", "in laws", "mother in law", "father in law"],
        "sibling": ["brother", "sister", "sibling", "bhai", "apu", "bon", "vaiya", "bhaiya", "apa", "didi", "little brother", "big sister"],
        "colleague": ["colleague", "boss", "coworker", "manager", "teammate", "client", "office friend", "senior at work", "office colleague"],
        "child": ["son", "daughter", "child", "kid", "nephew", "niece", "baby", "chele", "meye", "my little one"],
    },
}

# a relation word also tells us the gender (mom -> female). Used for training labels and understanding.
IMPLIED_GENDER = {
    "female": ["mother", "mom", "mum", "ammu", "maa", "ma", "sister", "apu", "apa", "bon", "didi", "daughter", "wife", "girlfriend", "bou",
               "fiancee", "meye", "aunt", "grandmother", "nani", "mother in law", "big sister"],
    "male": ["father", "dad", "baba", "abbu", "brother", "bhai", "vaiya", "bhaiya", "son", "husband", "boyfriend", "shami", "fiance", "chele",
             "uncle", "grandfather", "dadu", "father in law", "little brother"],
}
_IMPL = {w: g for g, ws in IMPLIED_GENDER.items() for w in ws}

FRAGMENTS = {
    "gender": ["for a {p}", "for {p}", "a {p}", "{p} gift", "gift for a {p}"],
    "age": ["{p}", "for a {p}", "who is {p}", "aged {p}", "for someone {p}"],
    "occasion": ["for {p}", "{p} gift", "{p} present", "for a {p}", "on {p}", "{p}", "something for {p}"],
    "hobby": ["who loves {p}", "who likes {p}", "into {p}", "{p} lover", "who enjoys {p}", "interested in {p}", "{p}", "likes {p}"],
    "profession": ["who is a {p}", "a {p}", "works as a {p}", "{p}", "for a {p}", "he is a {p}", "she is a {p}"],
    "relation": ["for my {p}", "my {p}", "gift for my {p}", "for {p}", "{p}", "buying for my {p}", "ami amar {p} er jonno"],
}
OPENERS = ["", "", "", "I need a gift", "suggest a gift", "gift idea", "what should I buy", "recommend something", "looking for a present",
           "help me find a gift", "need ideas", "gift lagbe", "ami ekta gift khujchi", "i want to buy something", "please suggest"]
BUDGET_NOISE = ["under {n}", "budget {n} taka", "within ${n}", "around {n} tk", "max {n}", "below {n}", "{n} taka budget", "budget is {n}"]

STOP = set("""a an the and or of to in on for with my me i im am is are be was it its this that these those he she him her his hers they them their
we us you your our gift gifts present presents idea ideas suggest suggestion recommend something anything need want looking find buy get give please help
under below within above over around about approximately budget max maximum min minimum less more than at least most up upto tk taka bdt usd dollar dollars
who whose what which some any ami amar ekta er jonno lagbe khujchi someone""".split())


# phrases that carry NO gift information. The model learns that they mean "no tag" (stops "premium" being read as "mum").
NEUTRAL = ["show me more", "something else", "another option", "different ideas", "more ideas", "cheaper please", "something cheaper", "too expensive",
           "something more premium", "more premium", "luxury", "something fancier", "better quality", "make it better", "nice", "ok", "okay", "thanks",
           "thank you", "great", "good", "can you help", "help me", "hello", "hi", "hey there", "assalamu alaikum", "salam", "what do you suggest",
           "any idea", "surprise me", "i don't know", "not sure", "something unique", "something special", "a nice present", "give me options",
           "show more", "aro dekhao", "onno kichu", "kom dami", "beshi dami", "ar ekta", "something practical", "something useful", "popular gifts",
           "best gifts", "trending", "gift", "gifts", "present", "a gift please", "idea", "premium quality", "affordable", "budget friendly"]


def phrases(dim, slug, options):
    """Training phrases for one option: seed words + the option's own label (so new admin options are learnt too)."""
    out = list(LEX.get(dim, {}).get(slug, []))
    label = options[dim][slug].lower()
    out.append(label)
    for chunk in re.split(r"[/&,()]| and ", label):
        chunk = re.sub(r"[^a-z0-9+' -]", " ", chunk).strip()
        if chunk and chunk not in out and len(chunk) > 2:
            out.append(chunk)
    out.append(slug.replace("-", " ").replace("plus", "+") if not slug[0].isdigit() else slug)
    return [p for p in dict.fromkeys(out) if p]


def _typo(rnd, text):
    """Small spelling mistakes so the model copes with real typing."""
    words = text.split()
    for _ in range(rnd.choice([1, 1, 2])):
        i = rnd.randrange(len(words))
        w = words[i]
        if len(w) > 4:
            j = rnd.randrange(1, len(w) - 1)
            kind = rnd.choice(["drop", "swap", "double", "vowel", "vowel"])
            if kind == "drop":
                w = w[:j] + w[j + 1:]
            elif kind == "vowel":
                vs = [k for k, ch in enumerate(w) if ch in "aeiou" and 0 < k]
                if vs:
                    k = rnd.choice(vs)
                    w = w[:k] + w[k + 1:]
            elif kind == "swap":
                w = w[:j] + w[j + 1] + w[j] + w[j + 2:]
            else:
                w = w[:j] + w[j] + w[j:]
            words[i] = w
    return " ".join(words)


def make_training_set(options, n=9000, seed=7):
    """Generate labelled queries from the catalog's own filter options. Label = 'dim:slug' (multi-label)."""
    rnd = random.Random(seed)
    dims = [d for d in options if options[d]]
    X, Y = [], []
    for _ in range(n):
        if rnd.random() < 0.10:                              # neutral-only sentence -> no labels at all
            X.append(rnd.choice(NEUTRAL))
            Y.append(set())
            continue
        k = rnd.choices([0, 1, 2, 3, 4], [4, 22, 34, 28, 12])[0]
        chosen = rnd.sample(dims, min(k, len(dims)))
        parts, labels = [], set()
        for d in chosen:
            slug = rnd.choice(list(options[d]))
            ph = rnd.choice(phrases(d, slug, options))
            labels.add(f"{d}:{slug}")
            if d == "relation" and ph in _IMPL and "gender" in options and _IMPL[ph] in options["gender"]:
                labels.add(f"gender:{_IMPL[ph]}")          # "mom" also means female
            parts.append(rnd.choice(FRAGMENTS.get(d, ["{p}"])).format(p=ph))
        rnd.shuffle(parts)
        text = (rnd.choice(OPENERS) + " " + " ".join(parts)).strip() or "gift"
        if rnd.random() < 0.15:
            text += " " + rnd.choice(NEUTRAL)                # neutral words sitting next to real ones
        if rnd.random() < 0.3:
            text += " " + rnd.choice(BUDGET_NOISE).format(n=rnd.choice([20, 30, 50, 100, 500, 1000, 2000, 3000, 5000]))
        if rnd.random() < 0.35:
            text = _typo(rnd, text)
        X.append(text.lower())
        Y.append(labels)
    return X, Y


# ---------------------------------------------------------------- budget + age parsing
_AGE_WORDS = r"(?:years?|yrs?|yo|y/o|year-old|years-old)"


def _num(s):
    return float(s.replace(",", ""))


def parse_budget(text, bdt_per_usd=120.0):
    """Find a price limit in the message. Returns {'min': usd|None, 'max': usd|None, 'label': str} or None.
    Understands: under $50, below 3000 taka, 500-1500 tk, between 20 and 40 dollars, around ৳2000, budget 2k ..."""
    t = text.lower().replace("৳", " ৳ ").replace("টাকা", " taka ")
    t = re.sub(r"\b\d{1,3}\s*[- ]?\s*" + _AGE_WORDS + r"(?:\s*-?\s*old)?", " ", t)          # "25 year old" is an age, not a price
    t = re.sub(r"\b\d0s\b", " ", t)                                                           # "30s"
    cur = r"(?:৳|tk\.?|taka|bdt|\$|usd|dollars?|bucks)"
    kw_max = r"(?:under|below|within|up\s*to|upto|less than|max(?:imum)?|not more than|at most|no more than|cheaper than)"
    kw_min = r"(?:above|over|at least|more than|min(?:imum)?|starting from|from)"
    kw_about = r"(?:around|about|approx(?:imately)?|roughly|near)"
    num = r"(\d[\d,]*(?:\.\d+)?)\s*(k)?"

    def to_usd(value, k, unit_text):
        v = _num(value) * (1000 if k else 1)
        unit = (unit_text or "").strip()
        usd = unit in ("$", "usd", "dollar", "dollars", "bucks")
        bdt = unit in ("৳", "tk", "tk.", "taka", "bdt")
        if not usd and not bdt:
            bdt = v > 400                      # bare 3000 -> taka, bare 50 -> dollars (the shop is priced in $)
        return (v if usd or not bdt else v / bdt_per_usd), bdt

    # range: 500-1500 tk / between 20 and 40 $
    m = re.search(r"(?:between\s+)?(" + cur + r")?\s*" + num + r"\s*(" + cur + r")?\s*(?:-|to|and)\s*(" + cur + r")?\s*" + num + r"\s*(" + cur + r")?", t)
    if m and (re.search(r"between|\bto\b|\band\b|-", t[m.start():m.end()])):
        u1, a, ak, u1b, u2, b, bk, u2b = m.groups()
        unit = u1b or u2b or u1 or u2
        lo, bdt = to_usd(a, ak, unit)
        hi, _ = to_usd(b, bk, unit)
        if lo > hi:
            lo, hi = hi, lo
        if (unit or hi > 5) :
            lab = f"${lo:.0f}-${hi:.0f}"
            return {"min": lo, "max": hi, "label": lab}

    m = re.search(r"(?:(" + kw_max + r"|" + kw_min + r"|" + kw_about + r"|budget(?:\s+is|\s+of)?)\s*[:=]?\s*)?(" + cur + r")?\s*" + num + r"\s*(" + cur + r")?", t)
    best = None
    for m in re.finditer(r"(?:(" + kw_max + r"|" + kw_min + r"|" + kw_about + r"|budget(?:\s+is|\s+of)?)\s*[:=]?\s*)?(" + cur + r")?\s*" + num + r"\s*(" + cur + r")?", t):
        kw, u_pre, val, k, u_suf = m.groups()
        unit = u_pre or u_suf
        if not kw and not unit:
            continue                           # a bare number without a price word is ignored
        usd, bdt = to_usd(val, k, unit)
        if usd <= 0:
            continue
        kw = (kw or "").strip()
        if re.match(kw_min, kw):
            res = {"min": usd, "max": None, "label": f"over ${usd:.0f}"}
        elif re.match(kw_about, kw):
            res = {"min": usd * 0.7, "max": usd * 1.3, "label": f"around ${usd:.0f}"}
        else:
            res = {"min": None, "max": usd, "label": f"up to ${usd:.0f}"}
        res["bdt"] = bdt
        best = res
        break
    return best


def parse_age(text):
    """'my 25 year old brother' -> 20-30. Returns an age option slug or None."""
    m = re.search(r"\b(\d{1,2})\s*[- ]?\s*" + _AGE_WORDS, text.lower())
    if not m:
        m = re.search(r"\b(?:age|aged)\s*(?:of\s*)?(\d{1,2})\b", text.lower())
    if not m:
        return None
    a = int(m.group(1))
    if a < 13:
        return "kid"
    if a < 20:
        return "teen"
    if a <= 30:
        return "20-30"
    if a <= 45:
        return "31-45"
    if a <= 60:
        return "46-60"
    return "60plus"


def clean_for_search(text):
    """Keep only the content words (perfume, watch, book...) for the product text match."""
    t = re.sub(r"[\d,.]+\s*(?:k\b)?", " ", text.lower().replace("৳", " "))
    words = [w for w in re.findall(r"[a-z']+", t) if w not in STOP and len(w) > 2]
    return " ".join(words)
