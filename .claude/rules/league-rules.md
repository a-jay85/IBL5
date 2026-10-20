---
description: Before changing game logic, check the change against the owner's league rules in ibl5/docs/LEAGUE_RULES.md and call out any rule it alters.
last_verified: 2026-10-20
paths: "ibl5/classes/**/*.php"
---

# League Rules Check

`ibl5/docs/LEAGUE_RULES.md` lists the rules the site must enforce. The league owner owns it.

Before you change code that enforces a rule there, read the matching section.

- If the change keeps every rule, carry on.
- If the change alters a rule, stop and ask the owner first. Name the rule and the old and new behavior.
- If you find code that already breaks a rule, report it. Leave the code and the rules file as they are until the owner decides.

When nobody is present to answer (a headless or automouse run), do not change the rule. Leave the code as it is. Put `League rule conflict:` and the rule in the PR body so the owner sees it.

When a PR changes a rule with the owner's approval, update `ibl5/docs/LEAGUE_RULES.md` in the same PR. Put `League rule changed:` and the rule in the PR body.
