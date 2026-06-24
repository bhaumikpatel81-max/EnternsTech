# CLAUDE.md — Enterns Tech Portal

## Project structure

```
EnternsTech/
├── wp-content/
│   ├── themes/
│   │   └── enternstech/          WordPress theme
│   └── plugins/
│       └── enterns-portal/       WordPress plugin (PHP 7.4+)
│           ├── enterns-portal.php    Plugin entry — defines ENP_VERSION, ENP_DIR, ENP_URL
│           ├── includes/
│           │   ├── install.php       Activation: tables, roles, WP pages
│           │   ├── config.php        Constants (email, plan IDs, Razorpay keys)
│           │   ├── payments.php      Razorpay order creation + webhook verification
│           │   ├── student.php       Student AJAX handlers
│           │   ├── shortcodes.php    [enp_admin], [enp_mentor], [enp_student], [enp_partner_form]
│           │   ├── psy-bank.php      AUTO-GENERATED — 178 question items as a PHP array
│           │   ├── psy-install.php   Psychometric tables + seed + helpers
│           │   ├── psy-resolver.php  ENP_Psy_Resolver — per-candidate paper builder
│           │   ├── psy-scorer.php    ENP_Psy_Scorer — scoring engine (static methods)
│           │   ├── psy-ajax.php      AJAX endpoints for candidate + admin psychometric flows
│           │   └── psy-shortcode.php [enp_psychometric] shortcode + asset enqueueing
│           ├── templates/
│           │   ├── student-dashboard.php
│           │   ├── mentor-dashboard.php
│           │   ├── partner-form.php
│           │   └── psy-candidate.php  Multi-step candidate assessment UI
│           ├── assets/
│           │   ├── css/portal.css     Dark-theme design system (--cyan, --bg, --surf, etc.)
│           │   ├── css/psychometric.css  Assessment-specific styles
│           │   └── js/psychometric.js    Candidate flow JS (multi-step, autosave, drag-and-drop rank)
│           └── tests/
│               └── psy-scorer-test.php  Unit tests — run: php wp-content/plugins/enterns-portal/tests/psy-scorer-test.php
├── admin-portal/
│   ├── index.php             Standalone admin portal (session auth + PDO + WP bootstrap)
│   └── config.php            DB creds (not committed)
└── docs/
    ├── HANDOFF_Psychometric_Module_v2.md   Full psychometric spec
    └── Enterns_Psychometric_QuestionBank.xlsx   Source question bank (178 items)
```

## Psychometric module

### Tables (prefix: `wp_`)
- `psy_items` — seeded from `psy-bank.php`; columns include `correct` and `reverse_scored`
- `psy_assessments` — one row per candidate link; token, region, edu_level, field, status, selected_items_json
- `psy_responses` — per-section autosave rows keyed by `(assessment_id, section)`
- `psy_scores` — one row per submitted assessment; all indices + bands + recommendation

### Question bank
`psy-bank.php` is auto-generated from `docs/Enterns_Psychometric_QuestionBank.xlsx` using:
```
node scripts/parse-bank.js   # (scratchpad one-off; not committed)
```
**Never edit `psy-bank.php` by hand.** Re-run the Node script if the Excel file changes.

178 items across 8 sections:
| Section | Type | Count |
|---------|------|-------|
| S1 | Likert (strengths) | 20 |
| S2 | Forced-choice A/B | 15 |
| S3 | Likert (learning) | 10 |
| S4 | Rank (motivation) | 10 |
| S5 | Likert (engagement) | 15 |
| S6 | Likert Big Five | 25 (5 traits × 5) |
| S7 | MCQ reasoning | 47 |
| S8 | Open text | varies |

### Resolver (ENP_Psy_Resolver)
- `resolve(bool $strip_sensitive)` — builds paper per candidate; random selection, region/edu/field filtered
- `resolve_and_persist(int $assessment_id)` — saves selected item IDs as JSON to DB; returns JSON string
- `rebuild_from_persisted(string $json, bool $strip_sensitive)` — restores paper from DB
- **Sec6**: exactly 3 items per Big Five trait (C/E/ES/O/A)
- **Sec7**: difficulty-weighted; prefers field-specific items; tops-up from ALL-field pool
- **Gap warning**: if pool too small, logs to `enp_psy_content_gaps` WP option + `error_log`

### Scoring (ENP_Psy_Scorer)
- Likert index: `(sum - min) / (max - min) × 100`
- Reverse scoring: `6 - raw` (applied when `reverse_scored = 'Y'`)
- Big Five normalisation: `(sum - n) / (n×5 - n) × 100` (min=n items × 1, max=n items × 5)
- Bands: ≥80 Strong | ≥60 Solid | ≥40 Mixed | <40 Watch
- Reasoning: x/6 correct; 5-6 strong | 3-4 adequate | ≤2 gap
- Sec2 (preference): A% ≥65 → Analytical | ≤35 → People | else Balanced
- Sec3 (learning): ≥75 self-directed | 50-74 capable-with-support | <50 needs-structured-onboarding
- `correct` and `reverse_scored` are **read from the DB** — never from client payload

### Security invariants
1. `correct` and `reverse_scored` are **never sent to the browser** — `strip_item()` removes them before any AJAX response
2. `enp_psy_ajax_autosave()` returns `{status:"ok"}` only
3. `enp_psy_ajax_submit()` returns `{status:"ok"}` only — score is never exposed to the candidate
4. The candidate assessment page (`psy-assessment`) sends `Cache-Control: no-store` + LiteSpeed no-cache hooks

### Assessment link
- Generated by admin; valid 7 days (`ENP_PSY_LINK_EXPIRY_DAYS = 7`)
- Token: `bin2hex(random_bytes(32))`
- Candidate accesses via `?t=TOKEN` — no WordPress login required
- Rate limit on submit: 5 attempts per hour (WP transients)

## Admin portal
- Lives at `/admin-portal/index.php` — not a WP page
- Session auth (username/password stored in `config.php`)
- Loads WP via `wp-load.php` for WP functions (mail, options, nonces)
- Sections: overview | payments | mentors | students | requests | assessments | sessions
- Assessments section sub-tabs: list | generate | settings (Razorpay toggle)
- Result view (`?section=assessments&view=ID`): shows all scores/bands/Big Five/reasoning/open responses + editable recommendation
- Razorpay per-product toggle: stored in WP option `enp_psy_rzp_plans`

## Running tests
```bash
php wp-content/plugins/enterns-portal/tests/psy-scorer-test.php
# Should print: N/N passed — all green
```
No WP or DB needed — tests use `ReflectionClass` to access private static methods.

## Conventions
- No score/band/feedback is shown to candidates — thank-you page only
- Admin result email goes to `admin@enternstech.com` on every submission
- All PHP files exit early if `ABSPATH` not defined (except standalone tools/tests)
- CSS variables in `portal.css` are the single source of truth for the design system
