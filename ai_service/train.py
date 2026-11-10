"""Train the gift AI from the command line and show a quick self-test.
   python train.py"""
import json
from recommender import Recommender

rec = Recommender()
rec._load_catalog()
print(f"Catalog: {len(rec.products)} products, options: { {d: len(o) for d, o in rec.options.items()} }")
print("Training the intent model ...")
meta = rec.train()
rec._build_index()
rec.ready = True
print(json.dumps(meta, indent=2))
print("\nSelf-test:")
for q in ["birthday gift for my mom under 3000 taka", "wedding present for my sister, she loves art", "something for a friend who loves tech",
          "anniversary surprise for my wife", "gift for a doctor under $60", "ami amar bhai er jonno gift khujchi, se gaan pore", "hi"]:
    out = rec.suggest([q])
    print(f"\n> {q}\n  understood: {out['understood']}")
    for g in out["gifts"]:
        print(f"   - {g['name']}  [{g['tag']}]  {g['price']}")
