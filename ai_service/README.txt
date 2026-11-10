UPOHAR GIFT AI (Python)
=======================
What it is
  A small recommendation model written in Python (scikit-learn). It reads what the visitor types
  ("birthday gift for my mom under 3000 taka"), works out the gift tags (gender, age, occasion, hobby,
  profession, who it is for) and the budget, and picks the best matching approved products from YOUR database.
  It understands English and Banglish (ammu, bhai, bondhu, biye, jonmodin ...), typing mistakes and
  follow-ups like "show me more", "cheaper", "something more premium".

How it learns ("training")
  * It is trained on thousands of example sentences generated from your filter options (Admin > Finder filters).
    Add a new hobby / profession / occasion in the admin panel and the AI learns it the next time it trains.
  * Products are indexed from your catalog. New / approved / hidden products are picked up automatically (within ~20 seconds).
  * Admin > AI model has a "Retrain" button. Training takes about 15 seconds.
  It is NOT a chat bot like ChatGPT: it only recommends gifts from your own shop, it never invents products.

Set-up (once)
  1. Install Python 3.9+ from https://www.python.org/downloads/  (tick "Add python.exe to PATH")
  2. XAMPP: start MySQL, and make sure database/upohar_admin.sql has been imported.
  3. Double-click  ai_service\start_ai.bat   (first time it installs the packages, about 2 minutes)
  4. Leave that black window open. Open the website > AI Suggestion and chat.
  If the AI window is closed, the AI page says "AI is offline" (or uses Gemini if you set a key in config.php).

Optional settings (config/config.php)
  define('BDT_PER_USD', 120);                      // taka per dollar; budgets like "3000 taka" are converted for the $ prices
  define('AI_PORT', 5055);                         // change if the port is taken
  define('AI_TOKEN', 'put-a-long-random-text');    // shared secret between PHP and Python
  define('AI_SERVICE_URL', 'http://127.0.0.1:5055');

Files
  app.py          the web service the website calls
  recommender.py  training + ranking
  nlp.py          words, budget / age reading, training-data generator
  db.py           reads products and tags from MySQL (login comes from config/config.php)
  train.py        python train.py  -> trains and prints a self-test
  model/          the trained model is saved here
