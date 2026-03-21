module.exports = {
  ci: {
    collect: {
      url: [
        'http://localhost:8080/ibl5/index.php',
        'http://localhost:8080/ibl5/modules.php?name=Standings',
        'http://localhost:8080/ibl5/modules.php?name=Team&op=team&teamID=1',
        'http://localhost:8080/ibl5/modules.php?name=Player&pa=showpage&pid=1',
        'http://localhost:8080/ibl5/modules.php?name=SeasonLeaderboards',
      ],
      numberOfRuns: 1,
      settings: {
        onlyCategories: ['performance', 'accessibility', 'best-practices'],
      },
    },
    assert: {
      assertions: {
        // Performance: warn at 0.6 — PHP-rendered pages score lower than SPAs.
        // Start lenient, tighten once baselines are established.
        'categories:performance': ['warn', { minScore: 0.6 }],
        // Accessibility: error at 0.85 — we enforce WCAG 2.1 AA via axe-core.
        // Lighthouse adds contrast ratios and tap target size checks.
        'categories:accessibility': ['error', { minScore: 0.85 }],
        // Best Practices: warn at 0.8 — catches mixed content, deprecated APIs.
        'categories:best-practices': ['warn', { minScore: 0.8 }],
      },
    },
    upload: {
      target: 'temporary-public-storage',
    },
  },
};
