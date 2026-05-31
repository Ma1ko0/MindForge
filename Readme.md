# MindForge

MindForge is a personal productivity and knowledge management tool designed to help you organize your thoughts, ideas, and daily workflow in one place.

It acts as a "second brain" tailored to three areas: **daily tasks**, **coding**, and **music** — capturing everything from quick notes and lyrics to coding tasks and project planning, while keeping it structured and accessible.

## ✨ Modules (current state)

- 📊 **Dashboard** — Stats, today's plan, today's habits, recent activity, quick-add
- ☀️ **Today / Workflow** — Time-blocked daily schedule + reusable templates
- 🔁 **Habits** — Recurring routines (daily, Mo–Fr, weekend, custom weekdays) with streak counter
- 📋 **Todo Lists** — Lists with items, due dates, completion tracking
- 📁 **Projects** — Workspaces grouping lists and notes; per-project stats and views
- 📝 **Notes** — Markdown notes with wiki-style `[[references]]` and backlinks
- 📅 **Daily Note** — One auto-templated note per day with today's tasks, blocks and journal sections
- 🏷 **Tags** — `#hashtag` syntax across notes, todos and workflow blocks; tag cloud with counts
- 📅 **Calendar** — Monthly overview with due-dated items as chips
- 🎵 **Lyrics** — Songs with `[Verse]`/`[Chorus]` sections, live German rhyme analysis (phonetic rule-based), rhyme scheme (A-B-A-B) and rhyme suggestions
- 🔍 **Global Search (Ctrl+K)** — Cross-module overlay search for notes, lists, items, blocks and projects
- 📲 **Installable PWA** — Manifest + service worker; install as a windowed app with offline app-shell

## 🚀 Roadmap

### Tier 1 — Closing the "second brain" gap ✅ done

- [x] Projects / Workspaces — hierarchy across modules
- [x] Global Search / Command Palette — `Ctrl+K` overlay across all modules
- [x] Tags — cross-cutting `#hashtag` labels
- [x] Daily Note (auto) — one note per day with aggregated header
- [x] Recurring tasks / habits — repeatable items with streak tracking

### Tier 2 — Polish, high impact

- `[[`-Autocomplete in the note editor
- Pin / Star important notes & lists → section on dashboard
- Note templates (Meeting, Book summary, Daily review)
- Archive (soft-delete with restore) instead of hard-delete
- Auto-save in note editor with status indicator
- Markdown toolbar (Bold / Italic / Link / List / Code)

### Music — Lyrics module ✅ done

- [x] Songs with section markers (`[Verse]` / `[Chorus]` / `[Bridge]`)
- [x] Rhyme detection — custom German phonetic rule-based analyzer (Auslautverhärtung, diphthong & long-vowel normalization on the rhyme tail) in `frontend/rhyme.js`
- [x] Rhyme scheme overview (A-B-A-B per section) + colored rhyme highlighting
- [x] Rhyme suggestions from a seed word list + the user's own growing lyrics corpus
- Future: syllable count / meter, larger bundled dictionary

### Tier 3 — Ambitious / future

- File and image upload + embed in notes
- Graph view of wiki references
- Note embed (`![[Note]]` renders content inline)
- Export / Import (Markdown bundle, JSON backup, Obsidian import)
- PWA + offline mode for mobile
- Spaced repetition for flashcards
- AI features (Q&A over notes, auto-tagging, summarization)
- Weekly review template with auto-aggregated stats

## 🚀 Vision

MindForge aims to replace scattered notes and tools with a single, focused system tailored to personal workflows — especially for developers and creatives.

## 📌 Status

Early development — Tier 1 ("second brain" foundation) complete; Tier 2 polish ahead.

---

> Build your mind. Forge your flow.
