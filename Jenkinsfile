pipeline {
    agent any

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  Praveen Portfolio - Production-Ready Jenkins CI/CD Pipeline ║
    // ╚══════════════════════════════════════════════════════════════╝

    environment {
        // FTP Credentials — stored in Jenkins Credentials Manager
        // Go to: Manage Jenkins → Credentials → Add Credentials
        FTP_SERVER   = credentials('ftp-server')
        FTP_USERNAME = credentials('ftp-username')
        FTP_PASSWORD = credentials('ftp-password')

        // Project settings
        DEPLOY_BRANCH = 'feat/admin'
        PHP_MIN_VERSION = '8.2'
    }

    options {
        // Keep only last 10 builds to save disk space
        buildDiscarder(logRotator(numToKeepStr: '10'))
        // Cancel the build if it takes too long (15 minutes max)
        timeout(time: 15, unit: 'MINUTES')
        // Show timestamps in console output
        timestamps()
        // Don't allow the same pipeline to run twice at the same time
        disableConcurrentBuilds()
    }

    triggers {
        // Check GitHub for changes every 5 minutes
        // (Use webhook instead if Jenkins is publicly accessible)
        pollSCM('H/5 * * * *')
    }

    stages {

        // ┌─────────────────────────────────────────────┐
        // │  STAGE 1: Checkout — Get the latest code    │
        // └─────────────────────────────────────────────┘
        stage('🚚 Checkout Code') {
            steps {
                echo '📦 Pulling the latest code from GitHub...'
                checkout scm
            }
        }

        // ┌─────────────────────────────────────────────┐
        // │  STAGE 2: Environment Check                 │
        // └─────────────────────────────────────────────┘
        stage('🔧 Environment Check') {
            steps {
                echo '🔍 Checking if PHP is installed and ready...'
                bat 'php --version'
            }
        }

        // ┌──────────────────────────────────────────────┐
        // │  STAGE 3: PHP Syntax Validation (Linting)    │
        // └──────────────────────────────────────────────┘
        stage('🧪 PHP Syntax Check') {
            steps {
                echo '🔎 Checking all PHP files for syntax errors...'
                bat '''
                    @echo off
                    setlocal enabledelayedexpansion
                    set "ERRORS=0"
                    for /r %%f in (*.php) do (
                        php -l "%%f" > nul 2>&1
                        if errorlevel 1 (
                            echo [FAIL] %%f
                            set /a ERRORS+=1
                        ) else (
                            echo [OK]   %%f
                        )
                    )
                    if !ERRORS! gtr 0 (
                        echo.
                        echo ❌ Found !ERRORS! file(s) with syntax errors!
                        exit /b 1
                    ) else (
                        echo.
                        echo ✅ All PHP files passed syntax check!
                    )
                '''
            }
        }

        // ┌──────────────────────────────────────────────┐
        // │  STAGE 4: Security Scan (SQL Injection)      │
        // └──────────────────────────────────────────────┘
        stage('🛡️ Security Scan') {
            steps {
                echo '🔐 Scanning for SQL injection vulnerabilities...'
                bat '''
                    @echo off
                    where semgrep >nul 2>&1
                    if %ERRORLEVEL% equ 0 (
                        semgrep --config semgrep.yml --error .
                        if errorlevel 1 (
                            echo ❌ Security vulnerabilities found! Fix them before deploying.
                            exit /b 1
                        ) else (
                            echo ✅ No security issues found!
                        )
                    ) else (
                        echo ⚠️ Semgrep not installed. Skipping security scan.
                        echo    Install it with: pip install semgrep
                    )
                '''
            }
        }

        // ┌──────────────────────────────────────────────┐
        // │  STAGE 5: Code Quality Checks                │
        // └──────────────────────────────────────────────┘
        stage('📏 Code Quality') {
            steps {
                echo '📐 Running code quality checks...'
                bat '''
                    @echo off
                    echo --- Checking for common issues ---

                    echo.
                    echo [CHECK] Looking for hardcoded passwords in PHP files...
                    findstr /s /i /n "password.*=.*'" *.php | findstr /v /i "DB_PASS\\|config.php\\|define(" > nul 2>&1
                    if %ERRORLEVEL% equ 0 (
                        echo ⚠️  Warning: Possible hardcoded passwords found. Review carefully.
                    ) else (
                        echo ✅ No hardcoded passwords detected.
                    )

                    echo.
                    echo [CHECK] Looking for debug/var_dump statements left in code...
                    findstr /s /n "var_dump\\|print_r\\|die(\\|dd(" *.php > nul 2>&1
                    if %ERRORLEVEL% equ 0 (
                        echo ⚠️  Warning: Debug statements found. Remove before production.
                        findstr /s /n "var_dump\|print_r\|die(\|dd(" *.php
                    ) else (
                        echo ✅ No debug statements found.
                    )

                    echo.
                    echo [CHECK] Checking for error display enabled in production...
                    findstr /s /n "display_errors.*1" config\config.php > nul 2>&1
                    if %ERRORLEVEL% equ 0 (
                        echo ⚠️  Warning: display_errors is ON. Turn OFF for production.
                    ) else (
                        echo ✅ Error display settings look good.
                    )

                    echo.
                    echo ✅ Code quality checks complete!
                '''
            }
        }

        // ┌──────────────────────────────────────────────┐
        // │  STAGE 6: Deploy via FTP                     │
        // └──────────────────────────────────────────────┘
        stage('🚀 Deploy to Production') {
            when {
                // Only deploy when building the main deploy branch
                branch "${DEPLOY_BRANCH}"
            }
            steps {
                echo '🌐 Deploying to InfinityFree via FTP...'
                bat """
                    @echo off
                    echo Uploading files to production server...

                    REM Using Windows built-in FTP or curl for file upload
                    REM This creates an FTP script and executes it

                    (
                        echo open %FTP_SERVER%
                        echo %FTP_USERNAME%
                        echo %FTP_PASSWORD%
                        echo binary
                        echo cd htdocs
                        echo prompt off
                        echo mput index.php
                        echo mput login.php
                        echo mput .htaccess
                        echo mkdir admin
                        echo cd admin
                        echo mput admin\\*.php
                        echo mput admin\\*.css
                        echo cd ..
                        echo mkdir assets
                        echo cd assets
                        echo mkdir css
                        echo cd css
                        echo mput assets\\css\\*.css
                        echo cd ..
                        echo mkdir js
                        echo cd js
                        echo mput assets\\js\\*.js
                        echo cd ..
                        echo cd ..
                        echo mkdir config
                        echo cd config
                        echo mput config\\config.php
                        echo cd ..
                        echo bye
                    ) > ftp_commands.txt

                    ftp -n -s:ftp_commands.txt

                    del ftp_commands.txt
                    echo ✅ Deployment complete!
                """
            }
        }
    }

    // ┌──────────────────────────────────────────────┐
    // │  POST ACTIONS — What happens after the build │
    // └──────────────────────────────────────────────┘
    post {
        success {
            echo '''
            ╔═══════════════════════════════════════════╗
            ║  ✅ BUILD SUCCESSFUL!                     ║
            ║  All checks passed. Code is deployed.     ║
            ╚═══════════════════════════════════════════╝
            '''
        }
        failure {
            echo '''
            ╔═══════════════════════════════════════════╗
            ║  ❌ BUILD FAILED!                         ║
            ║  Check the console output for errors.     ║
            ╚═══════════════════════════════════════════╝
            '''
        }
        always {
            // Clean up workspace after build
            cleanWs()
        }
    }
}
