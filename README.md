# FEI.edu → WordPress migration workspace

## Live demo (free, no account needed)

**[Open the demo on WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/programerrelaunch/fei-wordpress/main/blueprint.json)**

`blueprint.json` boots a fresh WordPress in the browser (WordPress.org's Playground), installs Kadence + Polylang from the plugin directory, installs `dist/fei-core.zip` and `dist/fei-kadence-child.zip` from this repo, then runs `playground/setup.php` (languages → programs → Home/Apply pages EN/ES → menu → landing pages). You are logged in as admin; `/wp-admin/` → **FEI** shows leads, settings and the integration log.

Things to try: `/programs/`, `/programs/medical-assistant/`, `/es/programas/asistente-medico/`, `/lp/medical-assistant/` (2-step LP), the **Apply Now** button (modal), `/apply/`, `/wp-json/fei/v1/lead` (POST), `/robots.txt`, `/wp-sitemap.xml`.

Playground limits: state is per browser tab (reload = fresh install), outbound HTTP from PHP is sandboxed, so n8n/Zoho forwards queue instead of delivering; everything else behaves as on a real host. Build the zips with `.\build.ps1`.

| Path | What it is |
|---|---|
| `blueprint.json`, `playground/setup.php`, `dist/` | One-click Playground demo + installable zips for any WordPress host |
| `AUDIT.md` *(local only, git-ignored)* | Phase 1 audit of the live Astro/Vercel site and the half-built Hostinger WordPress target; blockers; prioritized checklist |
| `wp-content/plugins/fei-core/` | The plugin that carries all business logic: programs CPT + seed, lead intake REST API (`/wp-json/fei/v1/lead`), n8n forward, Zoho CRM sync, follow-up automation, landing pages, templates, SEO/schema/hreflang, legacy redirects, hardening |
| `wp-content/themes/fei-kadence-child/` | Kadence child theme: FEI design tokens, header/footer/utility bar, block patterns for Home and Apply pages |
| `migration/` *(local only, git-ignored)* | Runbook, Zoho setup, CRO plan, URL map — see below |
| `migration/RUNBOOK.md` | Step-by-step staging → cutover → post-cutover runbook |
| `migration/ZOHO-SETUP.md` | OAuth self-client, field contract, dedupe/status webhooks, Zoho-side automation |
| `migration/CRO-PLAN.md` | What changed for conversion and the A/B hypotheses queue |
| `migration/url-map.csv` | Every EN/ES URL kept 1:1 plus legacy 301s (generated from `FEI_URL_Map`) |
| `tests/run.php` | Dependency-free PHP test suite for validator, Zoho mapper, URL map, program seed integrity, EN/ES string parity |

Program content source of truth: `wp-content/plugins/fei-core/data/programs.json` (verbatim from fei.edu; nothing invented). Seeded on plugin activation; re-run from FEI → Settings if programs are deleted.

## Local checks
```powershell
C:\xampp\php\php.exe tests\run.php
Get-ChildItem -Recurse -Filter *.php wp-content,tests | % { C:\xampp\php\php.exe -l $_.FullName }
node --check wp-content\plugins\fei-core\assets\js\fei-site.js
```

## Requirements
WordPress 6.5+, PHP 8.1+, Kadence theme, Polylang (for ES), Rank Math (optional; plugin degrades gracefully). Set `FEI_ZOHO_*` constants in `wp-config.php` — see `migration/ZOHO-SETUP.md`.
