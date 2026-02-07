FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    gnupg \
    curl \
    unixodbc \
    unixodbc-dev \
    libc-client-dev \
    libkrb5-dev \
    && rm -rf /var/lib/apt/lists/*

# Install Microsoft ODBC Driver 18 for SQL Server
RUN curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add - \
    && curl https://packages.microsoft.com/config/debian/11/prod.list > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    # REMOVE THE CONFLICTING PACKAGES FIRST
    && apt-get remove -y libodbc2 libodbccr2 libodbcinst2 unixodbc-common \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18

# Install PHP IMAP extension (must be compiled with kerberos support)
RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install imap

# Install PHP extensions for SQL Server
RUN pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# Install required PHP extensions
RUN docker-php-ext-install sockets pdo

# Install ODBC support
RUN docker-php-ext-configure pdo_odbc --with-pdo-odbc=unixODBC,/usr \
    && docker-php-ext-install pdo_odbc

# Copy custom php.ini
COPY php.ini /usr/local/etc/php/conf.d/

# Enable Apache modules
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]