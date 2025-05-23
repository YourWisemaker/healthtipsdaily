# HealthTipsDaily 🌿

> "The greatest wealth is health. Without health, life is not life; it is only a state of languor and suffering." — Herodotus

<p align="center">
<a href="https://laravel.com" target="_blank"><img src="https://img.shields.io/badge/Laravel-12.0-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel Version"></a>
<a href="https://php.net" target="_blank"><img src="https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP Version"></a>
<a href="https://whatsapp.com" target="_blank"><img src="https://img.shields.io/badge/WhatsApp-25D366?style=for-the-badge&logo=whatsapp&logoColor=white" alt="WhatsApp Integration"></a>
<a href="https://discord.com" target="_blank"><img src="https://img.shields.io/badge/Discord-5865F2?style=for-the-badge&logo=discord&logoColor=white" alt="Discord Integration"></a>
<a href="https://openrouter.ai" target="_blank"><img src="https://img.shields.io/badge/AI_Powered-OpenRouter-5A67D8?style=for-the-badge" alt="OpenRouter AI"></a>
</p>

## 📋 About HealthTipsDaily

HealthTipsDaily is a multi-platform health assistant that provides personalized daily health tips and answers health-related questions through both WhatsApp and Discord. The application uses AI to deliver evidence-based health information in a conversational manner, making health knowledge accessible to everyone through familiar messaging platforms.

### Key Features

- 🤖 **AI-Powered Health Assistant** - Get evidence-based answers to your health questions
- 📅 **Daily Health Tips** - Receive personalized health tips at your preferred time
- 🔍 **Personalized Experience** - Content tailored to your health interests
- 📱 **Multi-Platform Support** - Interact via WhatsApp or Discord
- 🎮 **Discord Slash Commands** - Quick access to health tips with `/healthtip` and `/subscribe`
- 🛡️ **Medical Disclaimer** - Clear guidance on when to consult healthcare professionals

## 🚀 Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js & NPM
- WhatsApp Business API credentials
- Discord Bot Token and Application ID
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

7. Configure WhatsApp, Discord, and OpenRouter API credentials in `.env`:
   ```
   WHATSAPP_VERIFY_TOKEN=your_verify_token
   WHATSAPP_API_TOKEN=your_api_token
   WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id
   
   DISCORD_WEBHOOK_URL=your_discord_webhook_url
   DISCORD_BOT_TOKEN=your_discord_bot_token
   DISCORD_GUILD_ID=your_discord_guild_id
   DISCORD_APPLICATION_ID=your_discord_application_id
   
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

10. For local development with webhook testing, use a service like ngrok:
    ```bash
    ngrok http 8000
    ```
    
11. Register Discord slash commands:
    ```bash
    php artisan discord:register-commands
    ```

## 🔧 Usage

### Messaging Platform Integrations

#### WhatsApp Integration

The application integrates with WhatsApp through webhooks. When a user sends a message to your WhatsApp Business number, the message is processed by the application and a response is generated using AI.

#### Discord Integration

The application integrates with Discord through bot interactions and webhooks. Users can interact with the bot in two ways:

1. **Direct Messages**: Users can send direct messages to the bot and receive AI-generated health information
2. **Slash Commands**: Users can use the following commands in any server where the bot is installed:
   - `/healthtip` - Get an instant health tip
   - `/subscribe [time]` - Subscribe to daily health tips at a specific time (format: HH:MM)

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

### Mass Messaging

You can send health tips to all users at once using the following command:

```bash
php artisan app:send-daily-health-tips --all
```

Additional options:
- `--limit=N` - Limit the number of messages to send
- `--force` - Send even if messages were already sent today

## 🧪 Testing

Run the test suite with:

```bash
php artisan test
```

## 📚 API Documentation

### Webhook Endpoints

#### WhatsApp Endpoints
- **POST /api/whatsapp/webhook** - Receives incoming WhatsApp messages
- **GET /api/whatsapp/webhook** - Verifies the webhook URL (required by WhatsApp Business API)

#### Discord Endpoints
- **POST /api/discord/webhook** - Handles Discord interactions and messages

## 🛠️ Architecture

The application follows Laravel's MVC architecture:

- **Models**: User, Conversation, MessageLog, ScheduledMessage
- **Controllers**: 
  - WhatsAppController handles WhatsApp messages and webhook verification
  - DiscordController handles Discord interactions and messages
- **Services**: 
  - OpenRouterService manages AI integration
  - DiscordService handles Discord API interactions

## 🔒 Security

- All WhatsApp communication is encrypted end-to-end by WhatsApp
- Discord communication uses Discord's secure API
- User data is stored securely and used only for providing the service
- API keys and tokens are stored as environment variables, not in the codebase

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## ⚠️ Disclaimer

HealthTipsDaily provides general health information and is not a substitute for professional medical advice. Always consult with a healthcare professional for medical concerns.
