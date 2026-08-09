# Switch Error Handler (`switch/error-handler`)

> Zero-config error and exception handler with Whoops-style dark debug pages, production error pages, JSON API error formatting, and PSR-15 middleware.

---

## 📦 Installation

```bash
composer require switch/error-handler
```

---

## ⚡ Zero-Config Usage with Switch Kernel

If you are using `switch/kernel`, `switch/error-handler` is **automatically detected and registered**. No code setup is required!

---

## 🛠️ Standalone Usage

```php
use Switch\ErrorHandler\ErrorHandler;
use Switch\ErrorHandler\Reporter\LogReporter;

// 1. Register global handler in debug mode (true = dev, false = prod)
$errorHandler = ErrorHandler::register(debug: true);

// 2. Add log reporter to record exceptions to file
$errorHandler->addReporter(new LogReporter(__DIR__ . '/storage/logs/app.log'));
```

---

## 🌐 PSR-15 Middleware

```php
use Switch\ErrorHandler\Middleware\ErrorHandlerMiddleware;
use Switch\ErrorHandler\ErrorHandler;

$middleware = new ErrorHandlerMiddleware(new ErrorHandler(debug: true));
$app->use($middleware);
```

---

## 🚨 Included HTTP Exceptions

```php
use Switch\ErrorHandler\Exception\NotFoundHttpException;
use Switch\ErrorHandler\Exception\ForbiddenHttpException;
use Switch\ErrorHandler\Exception\UnauthorizedHttpException;
use Switch\ErrorHandler\Exception\MethodNotAllowedHttpException;
use Switch\ErrorHandler\Exception\TooManyRequestsHttpException;

throw new NotFoundHttpException('User not found');
throw new MethodNotAllowedHttpException(['GET', 'POST']); // Sets Allow header
throw new TooManyRequestsHttpException(retryAfter: 60);  // Sets Retry-After header
```

---

## 🎨 Renderers

- **`HtmlRenderer`**: Dark theme Whoops-style debug UI with syntax-highlighted code snippets, stack frames, and HTTP request headers.
- **`ProductionHtmlRenderer`**: Customer-friendly error page hiding internal paths and secrets.
- **`JsonRenderer`**: Standard JSON responses for API endpoints.
- **`PlainTextRenderer`**: Clean terminal logs for CLI tasks.

---

## 📄 License
MIT License.
