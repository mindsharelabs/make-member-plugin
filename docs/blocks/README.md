# Custom Gutenberg Blocks

The Make Santa Fe Membership Plugin provides six custom Gutenberg blocks designed specifically for makerspace functionality. All blocks are built using Advanced Custom Fields (ACF) and are grouped under the "MAKE Santa Fe Blocks" category.

## Available Blocks

### 1. [Make Member Sign In](member-sign-in.md)

**Block Name**: `make-member-sign-in`

- **Purpose**: Allows members to sign in and track their visits
- **Features**: Badge tracking, user authentication, visit logging
- **Usage**: Place at entry points or member areas

### 2. [Upcoming Events](upcoming-events.md)

**Block Name**: `make-upcoming-events`

- **Purpose**: Displays upcoming events with ticket availability
- **Features**: Event filtering, ticket information, customizable display
- **Usage**: Homepage, events page, member dashboard

### 3. [Blog Category List](blog-categories.md)

**Block Name**: `make-blog-categories`

- **Purpose**: Displays selected blog categories in a formatted list
- **Features**: Category selection, custom styling
- **Usage**: Blog pages, resource sections

### 4. [Badge List](badge-list.md)

**Block Name**: `make-badge-list`

- **Purpose**: Displays member badges/certifications in card format
- **Features**: Badge filtering, card layout, certification tracking
- **Usage**: Member profiles, certification pages

### 5. [Image Slider](image-slider.md)

**Block Name**: `make-image-slider`

- **Purpose**: Simple image slider with navigation controls
- **Features**: Multiple images, captions, navigation arrows/dots
- **Usage**: Galleries, project showcases, event photos

### 6. [Instructor Bios](instructor-bios.md)

**Block Name**: `make-instructor-bios`

- **Purpose**: Displays instructor biographies for events
- **Features**: Instructor profiles, event integration
- **Usage**: Event pages, class descriptions

## Block Category

All blocks are organized under the custom category:

- **Category Slug**: `make-blocks`
- **Category Title**: "MAKE Santa Fe Blocks"
- **Category Icon**: Make Santa Fe logo (SVG)

## Common Features

### ACF Integration

- All blocks use Advanced Custom Fields for configuration
- Field groups are automatically registered
- Settings are stored as post meta

### Styling

- Consistent CSS framework across all blocks
- Responsive design principles
- Customizable through theme overrides

### JavaScript Enhancement

- Progressive enhancement approach
- jQuery-based interactions
- AJAX functionality where appropriate

### Accessibility

- Semantic HTML structure
- ARIA labels and roles
- Keyboard navigation support

## Block Architecture

### Registration Pattern

```php
acf_register_block_type(array(
    'name'              => 'block-name',
    'title'             => __('Block Title'),
    'description'       => __('Block description'),
    'render_template'   => MAKESF_ABSPATH . '/inc/templates/template-name.php',
    'category'          => 'make-blocks',
    'icon'              => MAKE_LOGO,
    'keywords'          => array('keyword1', 'keyword2'),
    'align'             => 'full',
    'mode'              => 'edit',
    'multiple'          => false,
    'supports'          => array('align' => false),
    'enqueue_assets'    => function() {
        // Asset loading logic
    }
));
```

### Template Structure

Each block has a corresponding PHP template in `/inc/templates/`:

- `make-member-sign-in.php`
- `make-upcoming-events.php`
- `make-blog-categories.php`
- `make-badge-list.php`
- `make-image-slider.php`
- `make-instructor-bios.php`

### Field Groups

ACF field groups are programmatically registered for each block, providing:

- Configuration options
- Content fields
- Display settings
- Validation rules

## Asset Management

### CSS Files

- `assets/css/style.css` - Main block styles
- `assets/css/slick-theme.css` - Slider theme styles
- `assets/css/stats.css` - Statistics and data display

### JavaScript Files

- `assets/js/make-member-sign-in.js` - Sign-in functionality
- `assets/js/slick.min.js` - Image slider library
- `assets/js/image-slider-init.js` - Slider initialization
- `assets/js/list.min.js` - List filtering and search
- `assets/js/stats.js` - Statistics display

### Asset Loading Strategy

- Assets are registered but not enqueued globally
- Each block enqueues only required assets
- Footer loading for better performance
- Version control using plugin version constant

## Customization

### Theme Override

Blocks can be customized by copying templates to your theme:

```
/wp-content/themes/your-theme/make-member-plugin/templates/
```

### CSS Customization

Override block styles in your theme's CSS:

```css
.make-block-container {
  /* Your custom styles */
}
```

### Hook Integration

Blocks provide action hooks for customization:

```php
// Before block content
do_action('make_block_before_content', $block);

// After block content
do_action('make_block_after_content', $block);
```

## Development Guidelines

### Adding New Blocks

1. Register block using `acf_register_block_type()`
2. Create template file in `/inc/templates/`
3. Add ACF field group configuration
4. Enqueue required assets
5. Add documentation

### Best Practices

- Use semantic HTML structure
- Implement progressive enhancement
- Follow WordPress coding standards
- Include proper sanitization and escaping
- Test across different themes
- Ensure mobile responsiveness

## Troubleshooting

### Blocks Not Appearing

1. Verify ACF is installed and activated
2. Check for JavaScript errors in browser console
3. Clear any caching plugins
4. Ensure proper user permissions

### Styling Issues

1. Check CSS file loading
2. Verify theme compatibility
3. Test with default WordPress theme
4. Check for CSS conflicts

### Functionality Problems

1. Review JavaScript console for errors
2. Check AJAX endpoints
3. Verify database table structure
4. Test with minimal plugin setup

## Next Steps

- [Configure individual blocks](member-sign-in.md)
- [Customize block appearance](../development.md#styling)
- [Set up member workflows](../api/README.md)
- [Monitor block performance](../troubleshooting.md)
