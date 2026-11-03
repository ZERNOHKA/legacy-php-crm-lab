#!/usr/bin/env python3
"""Check the running Apache/CGI/MariaDB demo with Python's standard library."""

import argparse
import http.cookiejar
from html.parser import HTMLParser
import json
import re
import sys
import urllib.error
import urllib.parse
import urllib.request


class ClientRows(HTMLParser):
    def __init__(self):
        super().__init__()
        self.in_body = False
        self.count = 0

    def handle_starttag(self, tag, attrs):
        if tag == "tbody":
            self.in_body = True
        elif tag == "tr" and self.in_body:
            self.count += 1

    def handle_endtag(self, tag):
        if tag == "tbody":
            self.in_body = False


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base-url", default="http://127.0.0.1:8080")
    args = parser.parse_args()
    base = args.base_url.rstrip("/")
    target = urllib.parse.urlsplit(base)
    if target.scheme != "http" or target.hostname not in ("127.0.0.1", "localhost", "::1"):
        parser.error("Use the local demo URL, for example http://127.0.0.1:8080")

    cookies = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies))

    def get(path):
        try:
            response = opener.open(base + path, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            return response.status, response.headers, response.read().decode("utf-8")

    def check(condition, label):
        if not condition:
            raise AssertionError(label)
        print("PASS " + label)

    code, _, body = get("/health")
    health = json.loads(body)
    check(code == 200 and health.get("ok") is True, "HTTP /health and .htaccess rewrite")
    check(health["php"] == "5.3.3" and health["sapi"] == "cgi-fcgi", "PHP 5.3.3 through CGI")
    check(health["ini"] == "/etc/php533/php.ini" and health["timezone"] == "UTC", "Web PHP loads the intended php.ini")
    check(health["missing_modules"] == [], "Required web modules are loaded")
    check(health["database"].startswith("5.5.") and health["clients"] == 5, "MariaDB 5.5 and five fictional clients")

    for path, expected in (("/", 5), ("/?status=active", 2), ("/?status=prospect", 2),
                           ("/?status=archived", 1), ("/?status=unknown", 5),
                           ("/?status%5B%5D=active", 5)):
        code, headers, html = get(path)
        rows = ClientRows()
        rows.feed(html)
        check(code == 200 and rows.count == expected, "Client filter " + path)
        check("Warning:" not in html and "Fatal error:" not in html and "<?php" not in html,
              "No PHP errors or source disclosure " + path)
        check(headers.get("X-Content-Type-Options") == "nosniff", "Response headers " + path)

    code, headers, html = get("/")
    check("Студия Север" in html, "UTF-8 data survives the database and CGI response")
    visits = re.search(r'id="session-visits">(\d+)</strong>', html)
    check(visits is not None and int(visits.group(1)) > 1, "Session cookie persists across requests")
    check(any(cookie.name == "PHPSESSID" for cookie in cookies), "PHP session cookie is set")
    check("httponly" in headers.get("Set-Cookie", "").lower()
          or any(cookie.has_nonstandard_attr("HttpOnly") or cookie.has_nonstandard_attr("httponly")
                 for cookie in cookies), "Session cookie is HttpOnly")

    for path in ("/includes/bootstrap.php", "/php53-cgi"):
        code, _, _ = get(path)
        check(code == 403, "Private path is blocked " + path)

    code, _, html = get("/index.php?-s")
    check(code == 200 and "<?php" not in html and "highlight_string" not in html,
          "HTTP query arguments do not switch CGI into source display mode")
    code, _, _ = get("/missing.php")
    check(code == 404, "Missing PHP file returns 404")
    print("Demo smoke checks passed.")


if __name__ == "__main__":
    try:
        main()
    except (AssertionError, urllib.error.URLError, json.JSONDecodeError) as error:
        print("FAIL " + str(error), file=sys.stderr)
        sys.exit(1)
