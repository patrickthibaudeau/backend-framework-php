#!/bin/bash

# DevFramework Development Scripts

case "$1" in
    "start")
        echo "🚀 Starting DevFramework development environment..."
        docker compose up -d
        echo "✅ Environment started!"
        echo "Web Application: http://localhost:8080"
        echo "Demo Page: http://localhost:8080/demo.html"
        echo "Health Check: http://localhost:8080/health"
        echo "PHP Container: devframework-php"
        echo "MySQL: localhost:3306 (user: devframework, pass: devframework)"
        echo "PostgreSQL: localhost:5432 (user: devframework, pass: devframework)"
        echo "Redis: localhost:6379"
        ;;

    "stop")
        echo "🛑 Stopping DevFramework development environment..."
        docker compose down
        echo "✅ Environment stopped!"
        ;;

    "build")
        echo "🔨 Building DevFramework containers..."
        docker compose build --no-cache
        echo "✅ Containers built!"
        ;;

    "shell")
        echo "🐚 Opening shell in PHP container..."
        docker compose exec web bash
        ;;

    "web")
        echo "🌐 Opening web application..."
        if command -v open >/dev/null 2>&1; then
            open http://localhost:8080/demo.html
        elif command -v xdg-open >/dev/null 2>&1; then
            xdg-open http://localhost:8080/demo.html
        else
            echo "Please open http://localhost:8080/demo.html in your browser"
        fi
        ;;

    "test")
        echo "🧪 Running configuration tests..."
        docker compose exec web php test-simple.php
        ;;

    "test-db")
        echo "🗄️ Running database tests..."
        docker compose exec web php test-database.php
        ;;

    "db")
        shift
        echo "🗄️ Running database command: $@"
        docker compose exec web php -r "
        require_once 'vendor/autoload.php';
        require_once 'src/Core/helpers.php';
        \$result = \$DB->$1;
        var_dump(\$result);
        "
        ;;

    "mysql")
        echo "🐬 Connecting to MySQL..."
        docker compose exec mysql mysql -u devframework -pdevframework devframework
        ;;

    "config")
        shift
        echo "⚙️ Running configuration command: $@"
        docker compose exec web php config-simple.php "$@"
        ;;

    "composer")
        shift
        echo "📦 Running Composer command: $@"
        docker compose exec web composer "$@"
        ;;

    "logs")
        echo "📋 Showing container logs..."
        docker compose logs -f web
        ;;

    "status")
        echo "📊 Container status:"
        docker compose ps
        ;;

    "clean")
        echo "🧹 Cleaning up containers and volumes..."
        docker compose down -v
        docker system prune -f
        echo "✅ Cleanup complete!"
        ;;

    *)
        echo "DevFramework Development Environment"
        echo ""
        echo "Usage: ./dev.sh <command>"
        echo ""
        echo "Commands:"
        echo "  start     - Start the development environment"
        echo "  stop      - Stop the development environment"
        echo "  build     - Build/rebuild containers"
        echo "  shell     - Open shell in PHP container"
        echo "  test      - Run configuration tests"
        echo "  test-db   - Run database tests"
        echo "  mysql     - Connect to MySQL database"
        echo "  config    - Run configuration commands"
        echo "  composer  - Run Composer commands"
        echo "  logs      - Show PHP container logs"
        echo "  status    - Show container status"
        echo "  clean     - Clean up containers and volumes"
        echo ""
        echo "Examples:"
        echo "  ./dev.sh start"
        echo "  ./dev.sh config init"
        echo "  ./dev.sh config validate"
        echo "  ./dev.sh composer install"
        echo "  ./dev.sh test"
        echo "  ./dev.sh test-db"
        echo "  ./dev.sh mysql"
        ;;
esac
