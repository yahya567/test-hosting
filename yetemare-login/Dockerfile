FROM apache2:latest

# Copy HTML files to Apache document root
COPY html/ /usr/local/apache2/htdocs/

# Enable mod_rewrite for clean URLs (optional)
RUN a2enmod rewrite

# Expose port 80
EXPOSE 80

# Start Apache in foreground
CMD ["httpd", "-D", "FOREGROUND"]
