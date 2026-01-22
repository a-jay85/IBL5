# Theme Demo Page

## Accessing the Demo

Navigate to: **`http://localhost/ibl5/demo.php`**

(Note: Your MAMP DocumentRoot is set to `/Users/ajaynicolas/Documents/GitHub/IBL5`, so you MUST include `/ibl5/` in the URL path)

If still redirecting, try the debug page first: **`http://localhost/ibl5/demo-debug.php`**

## What You'll See

The demo page showcases all new Tailwind CSS components and features:

### 1. **Mobile-First Header**
- Responsive navigation with hamburger menu on mobile
- League switcher dropdown
- User authentication status

### 2. **Component Library**
- **Cards** - Modern card layout for content sections
- **Tables** - Styled data tables with proper formatting
- **Alerts** - Success, info, warning, and error messages
- **Buttons** - Primary, secondary, success, and danger styles
- **Forms** - Input fields, selects, and textareas with Tailwind styling
- **Sidebar Boxes** - Compact navigation boxes

### 3. **Mobile Menu**
- Automatically extracts links from all sidebar blocks
- Categorizes into: Team, Stats, Site, Account, Other
- Responsive hamburger menu on mobile devices
- Click the ☰ icon (top-left on mobile) to open

### 4. **Responsive Grid Layouts**
- Mobile (< 640px): 1 column
- Tablet (640px - 1023px): 2 columns
- Desktop (≥ 1024px): 3-4 columns

## Testing the Mobile Menu

1. **Desktop View**: Resize browser to < 768px width
2. **Mobile Device**: Access from phone/tablet
3. **Click**: Hamburger icon (☰) in top-left corner
4. **Result**: See categorized navigation with all sidebar links

## Component Usage Examples

All examples are shown with live code snippets on the demo page. Copy-paste the usage patterns into your modules.

### Quick Examples

```php
// Card
echo ThemeComponents::openCard('Title', '<p>Content here</p>');

// Alert
echo ThemeComponents::alert('success', 'Operation successful!');

// Button
echo ThemeComponents::button('Click Me', 'primary', '/url');

// Table
echo ThemeComponents::table('<thead>...</thead><tbody>...</tbody>');
```

## Blade Templates

The header and footer use Blade templates located in:
- `themes/IBL/partials/header.blade.php`
- `themes/IBL/partials/footer.blade.php`

Rendered using `View\BladeRenderer`.

## Browser Compatibility

- ✅ Chrome/Edge (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

## Files Involved

| File | Purpose |
|------|---------|
| `demo.php` | Main demo page |
| `themes/IBL/dist/app.css` | Compiled Tailwind CSS (24KB) |
| `node_modules/alpinejs/dist/cdn.min.js` | Alpine.js for interactivity |
| `classes/View/BladeRenderer.php` | Template rendering |
| `classes/View/ThemeComponents.php` | UI component library |
| `classes/Navigation/MobileMenuBuilder.php` | Mobile menu system |

## Next Steps

After reviewing the demo:

1. **Approve the design**: Confirm visual style matches IBL branding
2. **Test mobile menu**: Verify all sidebar links are categorized correctly
3. **Integration**: Replace old theme.php functions with new components
4. **Migration**: Follow DEVELOPMENT_GUIDE.md for auth system deployment

## Notes

- All components use Tailwind CSS utility classes
- Colors match existing IBL theme (`#336699` for links, `#EEEEEE` for backgrounds)
- XSS protection via `htmlspecialchars()` in all components
- Mobile-first responsive design
- No JavaScript dependencies except Alpine.js (3KB gzipped)

## Troubleshooting

**CSS not loading?**
- Run: `npm run build:css`
- Check: `themes/IBL/dist/app.css` exists

**Mobile menu empty?**
- Check: `blocks/` directory exists and contains `.php` files
- Verify: Block files return valid HTML

**Alpine.js not working?**
- Check: `node_modules/alpinejs/dist/cdn.min.js` exists
- Run: `npm install` if missing

## Questions?

See full documentation in:
- `DEVELOPMENT_GUIDE.md` - Laravel Auth migration steps
- `CLAUDE.md` - Project architecture and patterns
- `.claude/rules/` - Coding standards
