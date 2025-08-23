@echo off
REM ZeroAI Local Setup Script for Windows
REM Zero Cost. Zero Cloud. Zero Limits.

echo 🚀 ZeroAI Local Setup
echo =====================

REM Check Python
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Python not found. Please install Python 3.8+
    pause
    exit /b 1
)

echo 🐍 Python found
python --version

REM Check pip
pip --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ pip not found. Please install pip
    pause
    exit /b 1
)

REM Install Ollama if not present
where ollama >nul 2>&1
if %errorlevel% neq 0 (
    echo 🤖 Please install Ollama from https://ollama.ai/download
    echo    Then run this script again
    pause
    exit /b 1
) else (
    echo ✅ Ollama found
)

REM Install Python dependencies
echo 📦 Installing Python dependencies...
pip install -r requirements.txt

REM Setup environment file
if not exist .env (
    echo ⚙️ Creating .env file...
    copy .env.example .env
    echo 📝 Please edit .env with your configuration
) else (
    echo ✅ .env file exists
)

REM Start Ollama
echo 🔄 Starting Ollama...
start /b ollama serve
timeout /t 3 /nobreak >nul

REM Pull default model
echo 📥 Checking for default model...
ollama list | findstr "llama3.1:8b" >nul
if %errorlevel% neq 0 (
    echo 📥 Downloading llama3.1:8b model...
    ollama pull llama3.1:8b
) else (
    echo ✅ Default model available
)

echo.
echo 🎉 ZeroAI setup complete!
echo.
echo 🚀 Quick start:
echo    python run_example.py
echo.
echo 📚 Documentation:
echo    type README.md
echo.
echo ⚙️ Configuration:
echo    notepad .env

pause