# HealthTipsDaily 🌿

> "The greatest wealth is health. Without health, life is not life; it is only a state of languor and suffering." — Herodotus

<p align="center">
<a href="https://laravel.com" target="_blank"><img src="https://img.shields.io/badge/Laravel-12.0-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel Version"></a>
<a href="https://php.net" target="_blank"><img src="https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP Version"></a>
<a href="https://whatsapp.com" target="_blank"><img src="https://img.shields.io/badge/WhatsApp-25D366?style=for-the-badge&logo=whatsapp&logoColor=white" alt="WhatsApp Integration"></a>
<a href="https://openrouter.ai" target="_blank"><img src="https://img.shields.io/badge/AI_Powered-OpenRouter-5A67D8?style=for-the-badge" alt="OpenRouter AI"></a>
</p>

## 📋 About HealthTipsDaily

HealthTipsDaily is a WhatsApp-based health assistant that provides personalized daily health tips and answers health-related questions. The application uses AI to deliver evidence-based health information in a conversational manner, making health knowledge accessible to everyone through the familiar WhatsApp interface.

### Key Features

- 🤖 **AI-Powered Health Assistant** - Get evidence-based answers to your health questions
- 📅 **Daily Health Tips** - Receive personalized health tips at your preferred time
- 🔍 **Personalized Experience** - Content tailored to your health interests
- 📱 **WhatsApp Integration** - Interact through a familiar messaging platform
- 🛡️ **Medical Disclaimer** - Clear guidance on when to consult healthcare professionals

## 🚀 Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js & NPM
- WhatsApp Business API credentials
- OpenRouter API key

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/healthtipsdaily.git
   cd healthtipsdaily
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Install JavaScript dependencies:
   ```bash
   npm install
   ```

4. Copy the environment file and configure it:
   ```bash
   cp .env.example .env
   ```

5. Generate application key:
   ```bash
   php artisan key:generate
   ```

6. Configure your database in the `.env` file:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=healthtipsdaily
   DB_USERNAME=root
   DB_PASSWORD=
   ```

7. Configure WhatsApp and OpenRouter API credentials in `.env`:
   ```
   WHATSAPP_VERIFY_TOKEN=your_verify_token
   WHATSAPP_API_TOKEN=your_api_token
   OPENROUTER_API_KEY=your_openrouter_api_key
   ```

8. Run database migrations:
   ```bash
   php artisan migrate
   ```

9. Start the development server:
   ```bash
   php artisan serve
   ```

10. For local development with WhatsApp webhook testing, use a service like ngrok:
    ```bash
    ngrok http 8000
    ```

## 🔧 Usage

### WhatsApp Integration

The application integrates with WhatsApp through webhooks. When a user sends a message to your WhatsApp Business number, the message is processed by the application and a response is generated using AI.

### User Onboarding

New users go through a simple onboarding process:
1. Welcome message and request for user's name
2. Collection of health interests (nutrition, fitness, mental health, etc.)
3. Setting preferred time for daily health tips

### Scheduled Messages

The application sends daily health tips to users at their preferred time using the Laravel scheduler. To enable this feature, set up a cron job to run the scheduler:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## 🧪 Testing

Run the test suite with:

```bash
php artisan test
```

## 📚 API Documentation

### Webhook Endpoints

- **POST /api/whatsapp/webhook** - Receives incoming WhatsApp messages
- **GET /api/whatsapp/webhook** - Verifies the webhook URL (required by WhatsApp Business API)

## 🛠️ Architecture

The application follows Laravel's MVC architecture:

- **Models**: User, Conversation, MessageLog, ScheduledMessage
- **Controllers**: WhatsAppController handles incoming messages and webhook verification
- **Services**: OpenRouterService manages AI integration

## 🔒 Security

- All WhatsApp communication is encrypted end-to-end by WhatsApp
- User data is stored securely and used only for providing the service
- API keys and tokens are stored as environment variables, not in the codebase

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## ⚠️ Disclaimer

HealthTipsDaily provides general health information and is not a substitute for professional medical advice. Always consult with a healthcare professional for medical concerns.
