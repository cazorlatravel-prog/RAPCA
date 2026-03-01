# CLAUDE.md — AI Assistant Guide for RAPCA

## Repository Overview

**RAPCA** is a project hosted at `cazorlatravel-prog/RAPCA`. This file provides context and conventions for AI assistants working in this codebase.

> This is a newly initialized repository. Update this document as the project structure, tooling, and conventions evolve.

## Project Structure

```
RAPCA/
├── CLAUDE.md          # AI assistant guide (this file)
└── (project files)    # To be added
```

As the project grows, update this section to reflect the directory layout, key modules, and entry points.

## Development Workflow

### Branch Naming

- Feature branches: `feature/<description>`
- Bug fixes: `fix/<description>`
- AI-generated branches: `claude/<descriptor>`

### Commit Messages

- Use clear, descriptive commit messages
- Start with a verb in imperative mood (e.g., "Add", "Fix", "Update", "Remove")
- Keep the subject line under 72 characters
- Add a blank line and body for complex changes

### Pull Requests

- Provide a summary of changes and motivation
- Reference related issues when applicable
- Keep PRs focused on a single concern

## Build & Test Commands

> **Note:** No build or test tooling has been configured yet. Update this section once a language, framework, and test runner are chosen.

<!-- Example placeholders — uncomment and adapt once tooling is set up:
```bash
# Install dependencies
npm install          # or: pip install -r requirements.txt

# Run tests
npm test             # or: pytest

# Lint / format
npm run lint         # or: ruff check .

# Build
npm run build        # or: make build
```
-->

## Code Style & Conventions

- Follow the conventions of the chosen language and framework once established
- Prefer readability over cleverness
- Keep functions small and focused
- Avoid premature abstraction — write the simplest code that solves the current problem
- Do not add comments for self-explanatory code; add them where intent is non-obvious

## Key Conventions for AI Assistants

1. **Read before editing** — Always read a file before modifying it. Understand existing patterns before suggesting changes.
2. **Minimal changes** — Only change what is requested. Do not refactor surrounding code, add unrelated improvements, or introduce new abstractions unless asked.
3. **No unnecessary files** — Prefer editing existing files over creating new ones. Do not create documentation files unless explicitly requested.
4. **Security first** — Never introduce vulnerabilities (injection, XSS, etc.). Never commit secrets, credentials, or `.env` files.
5. **Test your changes** — Run existing tests after making changes. If tests fail, fix the issue before committing.
6. **Commit discipline** — Make atomic commits. Each commit should represent a single logical change. Never amend commits without explicit permission.
7. **Ask when uncertain** — If requirements are ambiguous, ask for clarification rather than guessing.

## Environment & Tooling

- **Platform:** Linux
- **Git remote:** `cazorlatravel-prog/RAPCA`
- **Language/Framework:** TBD — update once chosen
- **Package manager:** TBD
- **CI/CD:** TBD

## Secrets & Sensitive Data

- Never commit `.env`, credentials, API keys, or tokens
- Use environment variables or a secrets manager for sensitive configuration
- Add sensitive file patterns to `.gitignore` once the project structure is established
