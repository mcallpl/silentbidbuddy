#!/bin/bash
# ============================================================
# START LOCAL DEVELOPMENT SERVER
# Easy command to start PHP dev server with proper settings
# Usage: ./start-local.sh
# ============================================================

set -e

PORT=${1:-8000}
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "=================================="
echo "STARTING LOCAL DEVELOPMENT SERVER"
echo "=================================="
echo ""

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "❌ ERROR: PHP is not installed"
    echo "Install PHP and try again"
    exit 1
fi

echo "✅ PHP version:"
php -v | head -1
echo ""

# Check if already running on this port
if lsof -Pi :$PORT -sTCP:LISTEN -t >/dev/null 2>&1; then
    echo "⚠️  Port $PORT is already in use"
    echo "Kill process with: lsof -ti:$PORT | xargs kill -9"
    exit 1
fi

echo "📂 Project directory: $PROJECT_DIR"
echo "🌐 Local URL: http://localhost:$PORT"
echo ""
echo "=================================="
echo "🚀 SERVER STARTING..."
echo "=================================="
echo ""
echo "Press Ctrl+C to stop the server"
echo ""

# Start PHP development server
cd "$PROJECT_DIR"
php -S localhost:$PORT -t .

