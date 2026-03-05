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
    }

    options {
        // Keep only last 10 builds to save disk space
        buildDiscarder(logRotator(numToKeepStr: '10'))
        // Cancel the build if it takes too long (15 minutes max)
        timeout(time: 15, unit: 'MINUTES')
        // Don't allow the same pipeline to run twice at the same time
        disableConcurrentBuilds()
    }

    triggers {
        // Check GitHub for changes every 5 minutes
        pollSCM('H/5 * * * *')
    }

    stages {

        // ──────────────────────────────────────────────
        //  STAGE 1: Checkout — Get the latest code
        // ──────────────────────────────────────────────
        stage('Checkout Code') {
            steps {
                echo 'Pulling the latest code from GitHub...'
                checkout scm
            }
        }

        // ──────────────────────────────────────────────
        //  STAGE 2: Environment Check
        // ──────────────────────────────────────────────
        stage('Environment Check') {
            steps {
                echo 'Checking if PHP is installed and ready...'
                powershell '''
                    & C:\\xampp\\php\\php.exe --version
                    if ($LASTEXITCODE -ne 0) {
                        Write-Error "PHP is not available at C:\\xampp\\php\\php.exe"
                        exit 1
                    }
                '''
            }
        }

        // ──────────────────────────────────────────────
        //  STAGE 3: PHP Syntax Validation (Linting)
        // ──────────────────────────────────────────────
        stage('PHP Syntax Check') {
            steps {
                echo 'Checking all PHP files for syntax errors...'
                powershell '''
                    $errors = 0
                    Get-ChildItem -Recurse -Filter *.php | ForEach-Object {
                        $output = & C:\\xampp\\php\\php.exe -l $_.FullName 2>&1
                        if ($LASTEXITCODE -ne 0) {
                            Write-Host "[FAIL] $($_.FullName)"
                            Write-Host "       $output"
                            $errors++
                        } else {
                            Write-Host "[OK]   $($_.FullName)"
                        }
                    }
                    Write-Host ""
                    if ($errors -gt 0) {
                        Write-Host "FAILED: Found $errors file(s) with syntax errors!"
                        exit 1
                    } else {
                        Write-Host "PASSED: All PHP files have valid syntax!"
                    }
                '''
            }
        }

        // ──────────────────────────────────────────────
        //  STAGE 4: Security Scan (SQL Injection)
        // ──────────────────────────────────────────────
        stage('Security Scan') {
            steps {
                echo 'Scanning for SQL injection vulnerabilities...'
                powershell '''
                    $semgrepPath = Get-Command semgrep -ErrorAction SilentlyContinue
                    if ($semgrepPath) {
                        Write-Host "Semgrep found. Running security scan..."
                        & semgrep --config semgrep.yml --error .
                        if ($LASTEXITCODE -ne 0) {
                            Write-Host "FAILED: Security vulnerabilities found! Fix them before deploying."
                            exit 1
                        } else {
                            Write-Host "PASSED: No security issues found!"
                        }
                    } else {
                        Write-Host "WARNING: Semgrep is not installed. Skipping security scan."
                        Write-Host "         Install it with: pip install semgrep"
                    }
                '''
            }
        }

        // ──────────────────────────────────────────────
        //  STAGE 5: Code Quality Checks
        // ──────────────────────────────────────────────
        stage('Code Quality') {
            steps {
                echo 'Running code quality checks...'
                powershell '''
                    Write-Host "--- Checking for common issues ---"
                    $warnings = 0

                    # Check 1: Debug statements
                    Write-Host ""
                    Write-Host "[CHECK] Looking for debug/var_dump statements left in code..."
                    $debugMatches = Select-String -Path "*.php","admin\\*.php" -Pattern "var_dump|print_r|dd[(]" -ErrorAction SilentlyContinue
                    if ($debugMatches) {
                        Write-Host "WARNING: Debug statements found. Remove before production."
                        $debugMatches | ForEach-Object { Write-Host "  $($_.Filename):$($_.LineNumber) - $($_.Line.Trim())" }
                        $warnings++
                    } else {
                        Write-Host "PASSED: No debug statements found."
                    }

                    # Check 2: Error display enabled
                    Write-Host ""
                    Write-Host "[CHECK] Checking for error display enabled in production..."
                    $displayErrors = Select-String -Path "config\\config.php" -Pattern "display_errors.*1" -ErrorAction SilentlyContinue
                    if ($displayErrors) {
                        Write-Host "WARNING: display_errors is ON in config.php. Consider turning OFF for production."
                        $warnings++
                    } else {
                        Write-Host "PASSED: Error display settings look good."
                    }

                    Write-Host ""
                    if ($warnings -gt 0) {
                        Write-Host "Code quality checks complete with $warnings warning(s)."
                        Write-Host "Warnings are advisory and do not block the build."
                    } else {
                        Write-Host "PASSED: All code quality checks passed!"
                    }
                '''
            }
        }

        // ──────────────────────────────────────────────
        //  STAGE 6: Deploy via FTP (PowerShell .NET)
        // ──────────────────────────────────────────────
        stage('Deploy to Production') {
            when {
                branch "${DEPLOY_BRANCH}"
            }
            steps {
                echo 'Deploying to InfinityFree via FTP...'
                powershell '''
                    $ErrorActionPreference = "Stop"

                    $server   = $env:FTP_SERVER
                    $username = $env:FTP_USERNAME
                    $password = $env:FTP_PASSWORD

                    if (-not $server -or -not $username -or -not $password) {
                        Write-Error "FTP credentials are missing! Check Jenkins Credentials Manager."
                        exit 1
                    }

                    $ftpBase = "ftp://${server}/htdocs"
                    $cred    = New-Object System.Net.NetworkCredential($username, $password)

                    # ── Helper: Upload a single file via FTP ──
                    function Upload-FtpFile($localPath, $remotePath) {
                        $uri = "${ftpBase}/${remotePath}"
                        try {
                            $client = New-Object System.Net.WebClient
                            $client.Credentials = $cred
                            $fileBytes = [System.IO.File]::ReadAllBytes($localPath)
                            $client.UploadData($uri, $fileBytes) | Out-Null
                            Write-Host "[OK]   $remotePath"
                        } catch {
                            Write-Host "[FAIL] $remotePath - $($_.Exception.Message)"
                            throw
                        } finally {
                            if ($client) { $client.Dispose() }
                        }
                    }

                    # ── Helper: Create a remote FTP directory ──
                    function Ensure-FtpDir($dirPath) {
                        $uri = "${ftpBase}/${dirPath}/"
                        try {
                            $request = [System.Net.FtpWebRequest]::Create($uri)
                            $request.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
                            $request.Credentials = $cred
                            $request.GetResponse() | Out-Null
                            Write-Host "[DIR]  Created $dirPath"
                        } catch {
                            # Directory likely already exists
                            Write-Host "[DIR]  $dirPath (already exists)"
                        }
                    }

                    Write-Host ""
                    Write-Host "========================================="
                    Write-Host "  Deploying to $server"
                    Write-Host "========================================="
                    Write-Host ""

                    # ── Create remote directories ──
                    Write-Host "--- Creating directories ---"
                    Ensure-FtpDir "admin"
                    Ensure-FtpDir "assets"
                    Ensure-FtpDir "assets/css"
                    Ensure-FtpDir "assets/js"
                    Ensure-FtpDir "config"
                    Write-Host ""

                    # ── Upload root files ──
                    Write-Host "--- Uploading root files ---"
                    Upload-FtpFile "index.php"    "index.php"
                    Upload-FtpFile "login.php"    "login.php"
                    if (Test-Path ".htaccess") {
                        Upload-FtpFile ".htaccess" ".htaccess"
                    }
                    Write-Host ""

                    # ── Upload admin files ──
                    Write-Host "--- Uploading admin files ---"
                    Get-ChildItem -Path "admin" -Filter "*.php" | ForEach-Object {
                        Upload-FtpFile $_.FullName "admin/$($_.Name)"
                    }
                    Get-ChildItem -Path "admin" -Filter "*.css" | ForEach-Object {
                        Upload-FtpFile $_.FullName "admin/$($_.Name)"
                    }
                    Write-Host ""

                    # ── Upload assets ──
                    Write-Host "--- Uploading assets ---"
                    Get-ChildItem -Path "assets/css" -Filter "*.css" -ErrorAction SilentlyContinue | ForEach-Object {
                        Upload-FtpFile $_.FullName "assets/css/$($_.Name)"
                    }
                    Get-ChildItem -Path "assets/js" -Filter "*.js" -ErrorAction SilentlyContinue | ForEach-Object {
                        Upload-FtpFile $_.FullName "assets/js/$($_.Name)"
                    }
                    Write-Host ""

                    # ── Upload config ──
                    Write-Host "--- Uploading config ---"
                    Upload-FtpFile "config/config.php" "config/config.php"
                    Write-Host ""

                    Write-Host "========================================="
                    Write-Host "  Deployment COMPLETE!"
                    Write-Host "========================================="
                '''
            }
        }
    }

    // ──────────────────────────────────────────────
    //  POST ACTIONS — What happens after the build
    // ──────────────────────────────────────────────
    post {
        success {
            echo 'BUILD SUCCESSFUL! All checks passed.'
        }
        failure {
            echo 'BUILD FAILED! Check the console output for errors.'
        }
    }
}
