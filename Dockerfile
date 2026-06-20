FROM php:8.3-apache

WORKDIR /var/www/html

# Keep container dependencies minimal; curl is required by index.php.
RUN apt-get update \
	&& apt-get install -y --no-install-recommends libcurl4-openssl-dev \
	&& docker-php-ext-install -j"$(nproc)" curl \
	&& rm -rf /var/lib/apt/lists/*

COPY . /var/www/html

RUN a2enmod rewrite

EXPOSE 80