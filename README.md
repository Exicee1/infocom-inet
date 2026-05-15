# Apache 2.4.67 + PHP-FPM Docker Setup
## Переменные окружения (.env)
- `SSL_CERT_FILE=/etc/ssl/certs/server.crt`
- `SSL_KEY_FILE=/etc/ssl/private/server.key`
- `DH_PARAMS_FILE=ssl/dhparam.pem`
- `VIRTUAL_HOSTS=localhost,example.com` (запятые разделяют хосты)
- `LOG_LEVEL=info`

## Структура проекта
```
.
├── .env                          # Переменные окружения
├── docker-compose.yml            # Конфигурация Docker Compose
└── docker/
    ├── apache/                   # Контейнер Apache 2.4.67
    │   ├── Dockerfile            # Официальный образ с поддержкой HTTP/2
    │   └── conf/                 # Базовые конфиги (копируются в контейнер)
    │       ├── httpd.conf        # Основной конфиг Apache
    │       ├── httpd-mpm.conf    # MPM Event для высокой производительности
    │       ├── httpd-vhost.conf  # Виртуальные хосты (HTTP->HTTPS редирект)
    │       ├── httpd-ssl.conf    # SSL/HTTP/2 настройки + DH параметры
    │       └── dhparam.conf      # DH параметры для ECDHE шифрования
    └── logs/                     # Логи контейнера Apache
```
## Хост система (изменяемые конфиги и сертификаты)
```
ssl/
├── dhparam.pem     # DH параметры для SSL (ECDHE, HTTP/2) - ОБЯЗАТЕЛЬНО!
├── server.crt      # Ваш SSL сертификат PEM (не self-signed!)
└── server.key      # Ваш приватный ключ PEM (не self-signed!)
```

## Установка и настройка SSL/DH параметров

### 1. Сгенерировать DH параметры (обязательно для ECDHE шифрования):
```bash
cd ssl
openssl dhparam -out dhparam.pem 2048
```

### 2. Получить SSL сертификат:
- **Для тестов**: используйте `makecert.exe` из OpenSSL или self-signed cert
- **Для продакшена**: Let's Encrypt (letsencrypt.org) или коммерческий CA

### 3. Проверка конфигурации Apache после установки:
```bash
docker-compose up -d
# Проверка логов:
docker logs apache
# Тестовый запрос:
curl -k https://localhost
```

## Быстрый старт

### Генерация DH параметров (обязательно!)
**На хосте:**
```bash
cd ssl/
openssl dhparam -out dhparam.pem 2048
```

**Важно:** Без `dhparam.pem` контейнер не запустится из-за отсутствия SSL/DH параметров.

### Получение реальных SSL сертификатов (Let's Encrypt)
```bash
cd ssl/
# Пример с Certbot: certbot --nginx -d ваш-домен.ru
```

## Структура проекта
```
.
├── .env                      # Переменные окружения
├── docker-compose.yml        # Конфигурация Docker Compose
└── docker/
    ├── apache/               # Контейнер Apache
    │   ├── Dockerfile        # Dockerfile для Apache 2.4.67
    │   └── conf/             # Базовые конфиги (копируются в контейнер)
    │       ├── httpd.conf      # Основной конфиг Apache
    │       ├── httpd-mpm.conf  # MPM Event конфигурация
    │       ├── httpd-vhost.conf# Виртуальные хосты
    │       └── dhparam.conf    # SSL/HTTP/2/DH параметры
    └── logs/                 # Логи контейнера Apache
```
## Хост система (изменяемые конфиги)
```
ssl/
├── dhparam.pem     # DH параметры для SSL (ECDHE, HTTP/2)
├── server.crt      # Ваш SSL сертификат PEM (не self-signed!)
└── server.key      # Ваш приватный ключ PEM (не self-signed!)
```
## Установка и запуск
```bash
# 1. Создайте папки ssl/ с вашими сертификатами
cd .
mkdir -p ssl
echo "Ваш сертификат" > ssl/server.crt
echo "Ваш ключ" > ssl/server.key
chmod 600 ssl/server.key

# 2. Запустите контейнеры
docker-compose up -d

# 3. Проверьте работу Apache
curl http://localhost:8080/health
curl https://localhost:8443/ -k  # -k для self-signed тестовых сертификатов
```
## Настройка SSL с реальными сертификатами
1. Получите сертификат от CA (Let's Encrypt, Comodo и т.д.)
2. Скопируйте в `ssl/server.crt` и `ssl/server.key`
3. Запустите контейнер - Apache автоматически использует ваши реальные сертификаты
4. Отключите self-signed тестовый сертификат: удалите или переименуйте Dockerfile conf/server.key и server.crt

## Изменение конфигов на хосте
```bash
# Редактируйте эти файлы напрямую:
./docker/apache/conf/httpd.conf        # Основной конфиг Apache
./docker/apache/conf/httpd-vhost.conf  # Виртуальные хосты
./docker/apache/conf/httpd-ssl.conf    # SSL/HTTP/2 настройки
```

После изменения - перезапустите контейнер:
```bash
docker-compose restart apache
```
## Обновление конфигов (рекомендуемый способ)
1. Внесите изменения в хост файлы
2. Закоммитьте их в репозиторий
3. Выполните `docker-compose pull && docker-compose up -d`
4. Apache автоматически перезагрузится с новыми конфигами
