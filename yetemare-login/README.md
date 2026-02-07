# Yetemare Login - Professional Computer Learning Platform

A beautiful, multilingual login page for Yetemare, an online learning platform for computer skills. Built with pure HTML, CSS, and JavaScript, deployed with Apache2 in Docker.

## Features

- **Professional Tech Design**: Clean, modern interface with deep slate blue primary color and teal accents
- **Multilingual Support**: Available in English, Amharic, Oromo, and Tigrinya
- **Login System**: Phone number and password authentication
- **Password Reset**: Secure password reset via phone number
- **Terms of Service**: Dedicated page with comprehensive terms
- **Responsive Design**: Works seamlessly on desktop, tablet, and mobile devices
- **Zero Dependencies**: Pure HTML, CSS, and JavaScript - no frameworks required
- **Docker Ready**: Simple Docker Compose setup for easy deployment

## Project Structure

```
yetemare-login/
├── html/
│   ├── index.html           # Main login page
│   ├── terms.html           # Terms of Service page
│   ├── css/
│   │   ├── style.css        # Login page styling
│   │   └── terms.css        # Terms page styling
│   └── js/
│       ├── app.js           # Main application logic
│       ├── api.js           # API service for backend calls
│       └── translations.js  # Multilingual translations
├── Dockerfile               # Docker image configuration
├── compose.yml              # Docker Compose configuration
└── README.md               # This file
```

## API Integration

The application integrates with the Falconvas API for authentication:

- **Login Endpoint**: `https://app.falconvas.com/check`
  - Parameters: `msisdn`, `password`, `shortcode`
  - Returns: `{ status: "success|error", message: "..." }`

- **Password Reset Endpoint**: `https://app.falconvas.com/reset`
  - Parameters: `msisdn`, `shortcode`
  - Returns: `{ status: "success|error", message: "..." }`

## Quick Start

### Using Docker Compose

1. **Build and run the application**:
   ```bash
   docker-compose up -d
   ```

2. **Access the application**:
   - Open your browser and navigate to `http://localhost`

3. **Stop the application**:
   ```bash
   docker-compose down
   ```

### Local Development

1. **Serve the HTML files** using any HTTP server:
   ```bash
   # Using Python 3
   cd html
   python3 -m http.server 8000
   
   # Using Node.js (with http-server)
   npx http-server html
   ```

2. **Access the application**:
   - Open your browser and navigate to `http://localhost:8000`

## Multilingual Support

The application supports four languages:
- **English** (en)
- **Amharic** (am)
- **Oromo** (or)
- **Tigrinya** (ti)

Language selection is persistent using browser localStorage. Users can switch languages via the dropdown in the top-right corner.

## Design Philosophy

The design follows a **Professional Tech** aesthetic:
- **Color Palette**: Deep slate blue (#1E3A5F) primary with teal (#0D9488) accents
- **Typography**: Sora font family for clean, modern appearance
- **Layout**: Asymmetric design with geometric pattern accents
- **Interactions**: Smooth transitions and micro-interactions for professional feel
- **Accessibility**: Proper contrast ratios and keyboard navigation support

## User Flows

### Login Flow
1. User enters phone number and password
2. Form validates input
3. Request sent to backend API
4. Backend returns success/error message
5. Success: User redirected to dashboard
6. Error: Message displayed, user can retry

### Password Reset Flow
1. User clicks "Forgot Password?"
2. Form switches to reset mode
3. User enters phone number
4. Request sent to backend API
5. Backend sends password via SMS
6. Success message displayed
7. Auto-redirect to login page after 2 seconds

### Terms of Service
- Accessible via link below login form
- Dedicated page with full terms
- Back button to return to login

## Customization

### Changing Colors
Edit the CSS variables in `html/css/style.css`:
```css
:root {
    --primary: #1E3A5F;        /* Primary color */
    --accent: #0D9488;         /* Accent color */
    --background: #FFFFFF;     /* Background */
    --foreground: #1A202C;     /* Text color */
    /* ... more colors ... */
}
```

### Adding Languages
1. Add new language object to `translations` in `html/js/translations.js`
2. Add option to language selector in `html/index.html`
3. Translations will automatically be available

### Modifying API Endpoints
Edit the constants in `html/js/api.js`:
```javascript
const API_HOST = 'https://app.falconvas.com';
const SHORTCODE = '9643';
```

## Browser Support

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Performance

- **Page Load**: < 1s (all assets inline or cached)
- **No External Dependencies**: Pure HTML/CSS/JS
- **Minimal Bundle Size**: ~50KB total
- **Responsive Images**: SVG icons for crisp display

## Security Considerations

- All API calls use HTTPS
- Password inputs use secure type="password"
- No sensitive data stored in localStorage (only language preference)
- CORS handled by backend API
- Form inputs validated client-side with server-side verification

## Troubleshooting

### Docker container won't start
```bash
# Check logs
docker-compose logs yetemare-login

# Rebuild image
docker-compose build --no-cache
```

### API calls failing
- Verify internet connection
- Check if `https://app.falconvas.com` is accessible
- Verify correct phone number format (e.g., 0912345678)
- Check browser console for detailed error messages

### Styling issues
- Clear browser cache (Ctrl+Shift+Delete)
- Check if CSS files are loading (F12 > Network tab)
- Verify file paths are correct

## License

© 2026 Yetemare. All rights reserved.

## Support

For issues or questions, contact: support@yetemare.com
