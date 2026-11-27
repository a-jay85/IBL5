---
description: All-Star and End-of-Year awards ballot submission, validation and results display.
last_verified: 2026-11-26
---

# Voting

Manages the IBL ballots: GMs get the All-Star ballot during the Regular Season and the End-of-Year awards ballot in every other phase, and admins can view results in an expander that has no voting-closed check. `VotingBallotService` assembles ballot candidate data from the League, Player, and Season modules. `VotingSubmissionService` validates each submission and saves it. It rejects own-team picks, All-Star positions with fewer or more than four picks, empty End-of-Year fields, and a player repeated within an End-of-Year category. The repository save methods update the row matching `team_name`, so a resubmission overwrites the earlier ballot. `VotingResultsController` composes `VotingResultsService` and `VotingResultsView` into an admin-only expander on the Voting page (`VotingController::showBallot()`). Entry point: `ibl5/modules/Voting/index.php`.

| Class | Role |
|---|---|
| `VotingBallotService` | Assembles ballot candidates from League/Player/Season |
| `VotingBallotView` | Renders the voting ballot |
| `VotingController` | Shows the ballot for the current season phase, handles both submit routes, and adds the results expander for admins |
| `VotingSubmissionService` | Validates submissions and saves them |
| `SubmissionResult` | Value object holding the success flag and all validation errors from one submission |
| `VotingSubmissionView` | Renders submission confirmation |
| `VotingResultsController` | Composes results service + view |
| `VotingResultsService` | Aggregates vote tallies |
| `VotingResultsView` | Renders the results tables inside the admin expander |
| `VotingRepository` | Database access for ballots and results |
