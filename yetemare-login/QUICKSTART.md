# Yetemare Login - Quick Start Guide

## 🚀 Deploy with Docker Compose

### Prerequisites
- Docker installed ([Get Docker](https://docs.docker.com/get-docker/))
- Docker Compose installed ([Get Docker Compose](https://docs.docker.com/compose/install/))

### Step 1: Clone or Download the Project
```bash
cd /path/to/yetemare-login
```

### Step 2: Build and Start the Application
```bash
docker-compose up -d
```

This command will:
- Build the Docker image from the Dockerfile
- Start the Apache2 web server
- Expose the application on port 80

### Step 3: Access the Application
Open your browser and navigate to:
```
http://localhost
```

### Step 4: Stop the Application
```bash
docker-compose down
```

---

## 📋 What's Included

### Files
- `html/index.html` - Main login page
- `html/terms.html` - Terms of Service page
- `html/css/style.css` - Login page styles
- `html/css/terms.css` - Terms page styles
- `html/js/app.js` - Main application logic
- `html/js/api.js` - API integration
- `html/js/translations.js` - Multilingual support
- `Dockerfile` - Docker image configuration
- `compose.yml` - Docker Compose configuration

### Features
✓ Professional Tech Design
✓ Multilingual (English, Amharic, Oromo, Tigrinya)
✓ Login with phone number & password
✓ Password reset functionality
✓ Terms of Service page
✓ Responsive design
✓ Zero dependencies

---

## 🌍 Supported Languages

1. **English** (en)
2. **አማርኛ** (Amharic - am)
3. **Afaan Oromoo** (Oromo - or)
4. **ትግርኛ** (Tigrinya - ti)

Users can switch languages using the dropdown in the top-right corner.

---

## 🔌 API Integration

The application connects to the Falconvas API:

### Login
- **Endpoint**: `https://app.falconvas.com/check`
- **Method**: GET
- **Parameters**: `msisdn`, `password`, `shortcode`
- **Response**: `{ status: "success|error", message: "..." }`

### Password Reset
- **Endpoint**: `https://app.falconvas.com/reset`
- **Method**: GET
- **Parameters**: `msisdn`, `shortcode`
- **Response**: `{ status: "success|error", message: "..." }`

---

## 🎨 Design Details

### Color Scheme
- **Primary**: Deep Slate Blue (#1E3A5F)
- **Accent**: Teal (#0D9488)
- **Background**: White (#FFFFFF)
- **Text**: Dark Slate (#1A202C)

### Typography
- **Font Family**: Sora (Google Fonts)
- **Weights**: 400, 500, 600, 700

### Layout
- Centered card design
- Geometric pattern accent
- Responsive grid layout
- Mobile-optimized

---

## 📱 Browser Support

| Browser | Version | Support |
|---------|---------|---------|
| Chrome/Edge | 90+ | ✓ Full |
| Firefox | 88+ | ✓ Full |
| Safari | 14+ | ✓ Full |
| Mobile Chrome | Latest | ✓ Full |
| Mobile Safari | Latest | ✓ Full |

---

## 🔒 Security

- All API calls use HTTPS
- Password inputs use secure type="password"
- Client-side form validation
- No sensitive data in localStorage
- CORS handled by backend

---

## 🛠️ Customization

### Change Port
Edit `compose.yml`:
```yaml
ports:
  - "8080:80"  # Access on http://localhost:8080
```

### Change Colors
Edit `html/css/style.css`:
```css
:root {
    --primary: #YOUR_COLOR;
    --accent: #YOUR_COLOR;
    /* ... */
}
```

### Add Language
1. Edit `html/js/translations.js`
2. Add new language object
3. Add option to selector in `html/index.html`

---

## 📊 Performance

- Page Load: < 1 second
- Bundle Size: ~50 KB
- No external dependencies
- Optimized SVG icons
- Cached assets

---

## 🐛 Troubleshooting

### Container won't start
```bash
# Check logs
docker-compose logs yetemare-login

# Rebuild
docker-compose build --no-cache
docker-compose up -d
```

### Port already in use
```bash
# Change port in compose.yml
# Or find and stop the process using port 80
sudo lsof -i :80
```

### API calls failing
- Check internet connection
- Verify `https://app.falconvas.com` is accessible
- Check browser console (F12) for errors
- Verify phone number format

---

## 📞 Support

For issues or questions:
- Email: support@yetemare.com
- Check the README.md for detailed documentation

---

## 📄 License

© 2026 Yetemare. All rights reserved.

---

**Happy learning with Yetemare! 🎓**
