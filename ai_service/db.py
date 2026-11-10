"""Reads the website database. DB login is taken from ../config/config.php, so there is one place to edit."""
import os
import re
import pymysql

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def php_config():
    """Parse define('KEY', 'value'); lines of config/config.php."""
    cfg = {}
    path = os.path.join(ROOT, "config", "config.php")
    try:
        text = open(path, encoding="utf-8").read()
    except OSError:
        return cfg
    pattern = r"define\(\s*'([A-Z_]+)'\s*,\s*(?:'((?:[^'\\]|\\.)*)'|([0-9.]+))\s*\)"
    for key, sval, nval in re.findall(pattern, text):
        cfg[key] = (sval if sval != "" else nval).replace("\\'", "'")
    return cfg


CFG = php_config()


def setting(name, default):
    """environment variable > config/config.php > default"""
    return os.environ.get(name) or os.environ.get("AI_" + name) or CFG.get(name, default)


def connect():
    host = str(setting("DB_HOST", "localhost"))
    port = 3306
    if ":" in host:
        host, p = host.rsplit(":", 1)
        port = int(p)
    kw = dict(user=setting("DB_USER", "root"), password=setting("DB_PASS", ""), database=setting("DB_NAME", "upohar"),
              charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor)
    sock = os.environ.get("AI_DB_SOCKET")          # only used for testing on Linux
    if sock:
        return pymysql.connect(unix_socket=sock, **kw)
    return pymysql.connect(host=host, port=port, **kw)


VISIBLE = ("p.is_active = 1 AND p.status = 'approved' AND "
           "(p.seller_id IS NULL OR EXISTS (SELECT 1 FROM sellers sv WHERE sv.id = p.seller_id AND sv.status = 'approved'))")


def load_catalog():
    """Returns (products, tags, options). tags = {product_id: {dim: set(slugs)}}; options = {dim: {slug: label}}."""
    con = connect()
    try:
        with con.cursor() as cur:
            cur.execute(
                "SELECT p.id, p.name, p.category, p.category_label, p.price, p.old_price, p.description, p.image, p.emoji, p.badge, "
                "p.seller_id, s.shop_name FROM products p LEFT JOIN sellers s ON s.id = p.seller_id WHERE " + VISIBLE + " ORDER BY p.id")
            products = list(cur.fetchall())
            cur.execute("SELECT type, slug, label FROM filter_options ORDER BY sort_order, id")
            options = {}
            for r in cur.fetchall():
                options.setdefault(r["type"], {})[r["slug"]] = r["label"]
            cur.execute("SELECT product_id, type, slug FROM product_tags")
            tags = {}
            for r in cur.fetchall():
                tags.setdefault(int(r["product_id"]), {}).setdefault(r["type"], set()).add(r["slug"])
    finally:
        con.close()
    for p in products:
        p["price"] = float(p["price"])
        p["old_price"] = float(p["old_price"]) if p["old_price"] is not None else None
    return products, tags, options


def fingerprint():
    """Cheap numbers that change when products, tags or filter options change (used to refresh automatically)."""
    con = connect()
    try:
        with con.cursor() as cur:
            cur.execute("SELECT COUNT(*) c, COALESCE(MAX(p.id),0) m, COALESCE(SUM(p.price),0) s FROM products p WHERE " + VISIBLE)
            a = cur.fetchone()
            cur.execute("SELECT COUNT(*) c FROM product_tags")
            b = cur.fetchone()
            cur.execute("SELECT COUNT(*) c, COALESCE(SUM(CRC32(CONCAT(type, slug, label))), 0) h FROM filter_options")
            o = cur.fetchone()
    finally:
        con.close()
    return (int(a["c"]), int(a["m"]), float(a["s"]), int(b["c"])), (int(o["c"]), int(o["h"]))
