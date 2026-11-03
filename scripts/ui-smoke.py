#!/usr/bin/env python3
"""Check the demo in a browser and save desktop/mobile screenshots."""

import argparse
from pathlib import Path
import urllib.parse

from playwright.sync_api import sync_playwright


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base-url", default="http://127.0.0.1:8080")
    parser.add_argument("--channel", help="Use an installed browser, for example msedge")
    parser.add_argument("--output-dir", type=Path, default=Path("artifacts/ui"))
    args = parser.parse_args()
    url = args.base_url.rstrip("/") + "/"
    target = urllib.parse.urlsplit(url)
    if target.scheme != "http" or target.hostname not in ("127.0.0.1", "localhost", "::1"):
        parser.error("Use the local demo URL, for example http://127.0.0.1:8080")
    args.output_dir.mkdir(parents=True, exist_ok=True)
    errors = []

    def record_console(message):
        if message.type in ("error", "warning"):
            errors.append(message.type + ": " + message.text)

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(channel=args.channel, headless=True)
        try:
            for name, width, height in (("desktop", 1365, 1000), ("mobile", 390, 844)):
                context = browser.new_context(viewport={"width": width, "height": height}, locale="ru-RU")
                page = None
                try:
                    page = context.new_page()
                    page.on("pageerror", lambda error: errors.append(str(error)))
                    page.on("console", record_console)
                    response = page.goto(url, wait_until="networkidle")
                    assert response.status == 200, name + ": HTTP status"
                    assert page.url == url, name + ": URL"
                    assert page.title() == "Legacy CRM Lab — демонстрационный стенд", name + ": title"
                    assert page.locator("h1").is_visible(), name + ": heading"
                    assert page.get_by_text("Студия Север", exact=True).is_visible(), name + ": UTF-8"
                    assert page.get_by_text("php.ini загружен", exact=True).is_visible(), name + ": php.ini"
                    assert page.get_by_text("Подключена", exact=True).is_visible(), name + ": database"
                    assert page.locator("tbody tr").count() == 5, name + ": initial clients"
                    assert page.evaluate("document.documentElement.scrollWidth <= window.innerWidth"), name + ": page overflow"
                    page.screenshot(path=str(args.output_dir / (name + ".png")), full_page=True)
                    visits = int(page.locator("#session-visits").inner_text())
                    for label, count in (("Активные", 2), ("Потенциальные", 2), ("Архив", 1), ("Все клиенты", 5)):
                        page.get_by_role("link", name=label, exact=True).click()
                        page.wait_for_load_state("networkidle")
                        visits += 1
                        assert page.locator("tbody tr").count() == count, name + ": filter " + label
                        assert page.get_by_role("link", name=label, exact=True).get_attribute("aria-current") == "page"
                        assert int(page.locator("#session-visits").inner_text()) == visits, name + ": session"
                    print("PASS " + name + ": page, clients, filters, session, layout")
                except Exception:
                    if page is not None and not page.is_closed():
                        page.screenshot(path=str(args.output_dir / (name + "-failure.png")), full_page=True)
                    raise
                finally:
                    context.close()
        finally:
            browser.close()
    assert not errors, "Browser errors: " + "; ".join(errors)
    print("PASS browser console: no errors or warnings")


if __name__ == "__main__":
    main()
