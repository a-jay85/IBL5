---
description: Assembles the Topics page combining league discussion topics and news articles.
last_verified: 2026-07-24
---

# Topics

Displays the league's Topics page, combining discussion topics with news articles. `TopicsService` assembles both data sources, and `TopicsView` renders the combined page. The `News/` subdirectory contains a self-contained sub-module (`NewsRepository`, `NewsService`, `NewsView`) for fetching and rendering news articles independently. Entry point: `ibl5/modules/Topics/index.php`.

| Class | Role |
|---|---|
| `TopicsService` | Assembles Topics page data (topics + news) |
| `TopicsRepository` | Queries league discussion topics |
| `TopicsView` | Renders the combined topics page |
| `News\NewsRepository` | Queries news articles |
| `News\NewsService` | Assembles news data |
| `News\NewsView` | Renders news articles |
