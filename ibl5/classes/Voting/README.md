---
description: All-Star ballot submission and results display with duplicate-vote prevention.
last_verified: 2026-07-24
---

# Voting

Manages the IBL All-Star ballot: GMs vote for All-Star representatives, and results are displayed after voting closes. `VotingBallotService` assembles ballot candidate data from the League, Player, and Season modules. `VotingSubmissionService` processes ballot submissions with duplicate-vote prevention. `VotingResultsController` composes `VotingResultsService` and `VotingResultsView` for the results page. Entry point: `ibl5/modules/Voting/index.php`.

| Class | Role |
|---|---|
| `VotingBallotService` | Assembles ballot candidates from League/Player/Season |
| `VotingBallotView` | Renders the voting ballot |
| `VotingSubmissionService` | Processes submissions with duplicate prevention |
| `VotingSubmissionView` | Renders submission confirmation |
| `VotingResultsController` | Composes results service + view |
| `VotingResultsService` | Aggregates vote tallies |
| `VotingResultsView` | Renders the results page |
| `VotingRepository` | Database access for ballots and results |
