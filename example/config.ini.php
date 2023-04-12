;<?php /*

; Logging
; -------

; The logging level, can be: debug, info, warning, error
log.level = "error"

; Path for logging
log.dir = "./logs"

; Application
; -----------

; The base URL of the application (for example: https://domain.com/app)
app.base_url = "http://localhost/dynart-micro"

; The root path of the application (for example: /var/www/domain.com)
app.root_path = "."

; Router
; ------

; Is the server uses rewrite for routing?
; Check the example .htaccess file for Apache server config.
router.use_rewrite = false

; The index file (usually index.php)
router.index_file = "index.php"

; The query parameter that will be used for routing.
router.route_parameter = "route"

; View
; ----

; The default folder for the views
view.default_folder = "~/views"

; Database
; --------

database.default.name = "micro"
database.default.dsn = "mysql:localhost"
database.default.username = "root"
database.default.password = ""

; Translation
; -----------

; All translations locale that the application has
translation.all = "hu, en"

; The default locale if the browser didn't set it in the Accept-Language
; or the accepted language doesn't present in the all translations
translation.default = "hu"

; Mailer
; ------

; Is the mailer send fake emails? If it is true,
; it will only log the email on info level.
mailer.fake = false

; The mail server options
mailer.host = ""
mailer.port = ""
mailer.username = ""
mailer.password = ""

; Is the mailer uses an SMTP authentication?
mailer.smtp_auth = true

; How the SMTP is secured? Can be: tls or ssl
mailer.smtp_secure = "ssl"

; Should PHPMailer verifying SSL certs?
mailer.verify_ssl = false

; The debug level for PHPMailer
mailer.debug_level = 0

; The character set for PHPMailer
mailer.charset = "UTF-8"

; The encoding type for PHPMailer
mailer.encoding = "quoted-printable"

; The email sender
mailer.from_email = "info@yourdomain.com"
mailer.from_name = "From Name"

;*/