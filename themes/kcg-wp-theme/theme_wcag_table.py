#!/usr/bin/env python3
"""Generate a WCAG AA report for theme palette combinations.

This script reads theme.json from the current theme directory and writes an
HTML report showing meaningful background / heading / text combinations in
light and dark modes.
"""

import argparse
import html
import json
import itertools
from pathlib import Path

# Only include the core brand and neutral palette colors in the preview.
MAIN_COLORS = ["primary", "secondary", "accent", "inverse", "base", "base-inverse", "surface", "muted", "contrast", "contrast-inverse", "contrast-accent", "mono"]

def parse_color(value: str) -> dict:
    value = value.strip()
    if value.startswith("light-dark(") and value.endswith(")"):
        inner = value[len("light-dark("):-1]
        parts = [part.strip() for part in inner.split(",")]
        if len(parts) != 2:
            raise ValueError(f"Invalid light-dark format: {value}")
        return {"light": parts[0], "dark": parts[1]}
    return {"light": value, "dark": value}


def hex2rgb(value: str) -> tuple[float, float, float]:
    value = value.lstrip("#")
    if len(value) == 3:
        value = "".join(ch * 2 for ch in value)
    return tuple(int(value[i : i + 2], 16) / 255 for i in range(0, 6, 2))


def linearize(channel: float) -> float:
    if channel <= 0.03928:
        return channel / 12.92
    return ((channel + 0.055) / 1.055) ** 2.4


def relative_luminance(hex_color: str) -> float:
    r, g, b = hex2rgb(hex_color)
    return 0.2126 * linearize(r) + 0.7152 * linearize(g) + 0.0722 * linearize(b)


def contrast_ratio(foreground: str, background: str) -> float:
    l1 = relative_luminance(foreground)
    l2 = relative_luminance(background)
    lighter = max(l1, l2)
    darker = min(l1, l2)
    return (lighter + 0.05) / (darker + 0.05)


def format_check(ratio: float, threshold: float = 4.5) -> str:
    return "✅" if ratio >= threshold else "❌"


def best_text_color(background: str) -> str:
    black = contrast_ratio("#000000", background)
    white = contrast_ratio("#ffffff", background)
    return "#000000" if black >= white else "#ffffff"


def load_palette(theme_path: Path) -> dict[str, dict[str, str]]:
    with theme_path.open("r", encoding="utf-8") as fh:
        theme = json.load(fh)

    palette = theme["settings"]["color"]["palette"]
    return {item["slug"]: parse_color(item["color"]) for item in palette}


def build_html_table(palette: dict[str, dict[str, str]], output_path: Path | None) -> str:
    def render_cell(background: str, text: str, mode: str) -> str:
        bg_color = palette[background][mode]
        text_color = palette[text][mode]
        ratio_text = contrast_ratio(text_color, bg_color)
        ratio_summary = (
            f"<div style='margin-top:0.5rem; font-size:0.78rem;'>"
            f"Text: {ratio_text:.2f} {format_check(ratio_text)}"
            f"</div>"
        )
        cell_text_color = best_text_color(bg_color)
        return (
            f"<div style='background:{bg_color}; padding:0.75rem; min-height:120px;"
            f" color:{cell_text_color}; border:1px solid {best_text_color('#ffffff' if bg_color != '#ffffff' else '#000000')};"
            f" border-radius:0.5rem; margin:0.25rem;'>"
            f"<div style='font-size:1rem; font-weight:700; color:{text_color};'>Text sample</div>"
            f"{ratio_summary}"
            f"</div>"
        )

    def passes_both_modes(background: str, text: str) -> bool:
        light_bg = palette[background]["light"]
        dark_bg = palette[background]["dark"]
        light_text = palette[text]["light"]
        dark_text = palette[text]["dark"]

        light_text_ratio = contrast_ratio(light_text, light_bg)
        dark_text_ratio = contrast_ratio(dark_text, dark_bg)

        return light_text_ratio >= 4.5 and dark_text_ratio >= 4.5

    available_backgrounds = [key for key in MAIN_COLORS if key in palette]
    available_texts = [key for key in MAIN_COLORS if key in palette]
    rows = []
    for background, text in itertools.product(available_backgrounds, available_texts):
        if not passes_both_modes(background, text):
            continue
        light_cell = render_cell(background, text, "light")
        dark_cell = render_cell(background, text, "dark")
        rows.append((background, text, light_cell, dark_cell))

    html_lines = [
        "<!doctype html>",
        "<html lang='en'>",
        "<head>",
        "<meta charset='utf-8'>",
        "<meta name='viewport' content='width=device-width, initial-scale=1'>",
        "<title>WCAG Theme Palette Table</title>",
        "<style>",
        "body { font-family: system-ui, sans-serif; padding: 1rem; background:#f5f7fb; color:#111; }",
        "table { border-collapse: collapse; width: 100%; table-layout: fixed; }",
        "th, td { border: 1px solid #ccc; padding: 0.5rem; vertical-align: top; }",
        "th { background: #222; color: #fff; }",
        "td { word-break: break-word; }",
        ".cell-wrap { width: 100%; min-width: 220px; }",
        "</style>",
        "</head>",
        "<body>",
        "<h1>WCAG AA Preview: Light and Dark Palette Combinations</h1>",
        "<p>Each preview cell uses the background color as the cell background and the chosen text color for the sample text.</p>",
        "<table>",
        "<thead><tr><th>Background</th><th>Text</th><th>Light mode</th><th>Dark mode</th></tr></thead>",
        "<tbody>",
    ]

    for background, text, light_cell, dark_cell in rows:
        html_lines.append(
            "<tr>"
            f"<td>{html.escape(background)}</td>"
            f"<td>{html.escape(text)}</td>"
            f"<td class='cell-wrap'>{light_cell}</td>"
            f"<td class='cell-wrap'>{dark_cell}</td>"
            "</tr>"
        )

    html_lines.extend([
        "</tbody>",
        "</table>",
        "</body>",
        "</html>",
    ])

    html_content = "\n".join(html_lines)
    if output_path:
        output_path.write_text(html_content, encoding="utf-8")
    return html_content


def main() -> None:
    parser = argparse.ArgumentParser(description="Generate a WCAG AA palette contrast table from theme.json.")
    parser.add_argument(
        "--theme",
        type=Path,
        default=Path("theme.json"),
        help="Path to theme.json",
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=Path("wcag_palette_table.html"),
        help="Path to write the HTML report",
    )
    args = parser.parse_args()

    palette = load_palette(args.theme)
    html_content = build_html_table(palette, args.output)

    print(f"WCAG table written to {args.output}")


if __name__ == "__main__":
    main()
