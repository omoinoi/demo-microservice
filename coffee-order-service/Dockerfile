FROM php:8.2-cli

# Cài đặt các thư viện hệ thống cần thiết
RUN apt-get update && apt-get install -y libzip-dev unzip librdkafka-dev \
    && docker-php-ext-install pdo_mysql zip

# Cài đặt Redis và Kafka extension
RUN pecl install redis rdkafka && docker-php-ext-enable redis rdkafka

# THÊM DÒNG NÀY: Copy Composer từ image chính thức của Composer bỏ vào container của chúng ta
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app