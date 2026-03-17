pipeline {
    agent any

    options {
        buildDiscarder(logRotator(numToKeepStr: '10'))
        disableConcurrentBuilds()
        timeout(time: 15, unit: 'MINUTES')
    }

    triggers {
        // Poll GitHub every 5 minutes for changes on the deploy branch
        pollSCM('H/5 * * * *')
    }

    environment {
        FTP_SERVER   = credentials('ftp-server')
        FTP_USERNAME = credentials('ftp-username')
        FTP_PASSWORD = credentials('ftp-password')
        LOCAL_DEPLOY_DIR = 'C:\\Users\\Admin\\Documents\\Portfolio\\ci-cd'
    }

    stages {

        stage('Checkout') {
            steps {
                echo '📥 Pulling latest approved code from deploy branch...'
                checkout scm
            }
        }

        stage('Deploy via FTP') {
            steps {
                echo '🚀 Deploying to FTP server...'
                bat '''
                    @echo off
                    echo ============================================
                    echo   Uploading files to FTP server...
                    echo ============================================

                    REM Upload PHP files
                    for /R "%WORKSPACE%" %%F in (*.php) do (
                        set "fullpath=%%F"
                        setlocal enabledelayedexpansion
                        set "relpath=!fullpath:%WORKSPACE%\\=!"
                        echo Uploading: !relpath!
                        curl -s -T "%%F" "ftp://%FTP_SERVER%/htdocs/!relpath!" --user "%FTP_USERNAME%:%FTP_PASSWORD%" --ftp-create-dirs
                        endlocal
                    )

                    REM Upload CSS files
                    for /R "%WORKSPACE%" %%F in (*.css) do (
                        set "fullpath=%%F"
                        setlocal enabledelayedexpansion
                        set "relpath=!fullpath:%WORKSPACE%\\=!"
                        echo Uploading: !relpath!
                        curl -s -T "%%F" "ftp://%FTP_SERVER%/htdocs/!relpath!" --user "%FTP_USERNAME%:%FTP_PASSWORD%" --ftp-create-dirs
                        endlocal
                    )

                    REM Upload JS files
                    for /R "%WORKSPACE%" %%F in (*.js) do (
                        set "fullpath=%%F"
                        setlocal enabledelayedexpansion
                        set "relpath=!fullpath:%WORKSPACE%\\=!"
                        echo Uploading: !relpath!
                        curl -s -T "%%F" "ftp://%FTP_SERVER%/htdocs/!relpath!" --user "%FTP_USERNAME%:%FTP_PASSWORD%" --ftp-create-dirs
                        endlocal
                    )

                    REM Upload SQL files
                    for /R "%WORKSPACE%" %%F in (*.sql) do (
                        set "fullpath=%%F"
                        setlocal enabledelayedexpansion
                        set "relpath=!fullpath:%WORKSPACE%\\=!"
                        echo Uploading: !relpath!
                        curl -s -T "%%F" "ftp://%FTP_SERVER%/htdocs/!relpath!" --user "%FTP_USERNAME%:%FTP_PASSWORD%" --ftp-create-dirs
                        endlocal
                    )

                    REM Upload .htaccess
                    if exist "%WORKSPACE%\\.htaccess" (
                        echo Uploading: .htaccess
                        curl -s -T "%WORKSPACE%\\.htaccess" "ftp://%FTP_SERVER%/htdocs/.htaccess" --user "%FTP_USERNAME%:%FTP_PASSWORD%" --ftp-create-dirs
                    )

                    echo ============================================
                    echo   FTP Upload Complete!
                    echo ============================================
                '''
            }
        }

        stage('Update Local Directory') {
            steps {
                echo '📂 Copying code to local ci-cd directory...'
                bat '''
                    @echo off
                    echo ============================================
                    echo   Updating local directory...
                    echo ============================================

                    REM Create the target directory if it doesn't exist
                    if not exist "%LOCAL_DEPLOY_DIR%" (
                        mkdir "%LOCAL_DEPLOY_DIR%"
                    )

                    REM Clean old files (except .git if present)
                    if exist "%LOCAL_DEPLOY_DIR%\\.git" (
                        echo Preserving .git directory...
                        for /D %%D in ("%LOCAL_DEPLOY_DIR%\\*") do (
                            if /I not "%%~nxD"==".git" rmdir /S /Q "%%D"
                        )
                        for %%F in ("%LOCAL_DEPLOY_DIR%\\*") do (
                            del /Q "%%F"
                        )
                    ) else (
                        if exist "%LOCAL_DEPLOY_DIR%" (
                            rmdir /S /Q "%LOCAL_DEPLOY_DIR%"
                            mkdir "%LOCAL_DEPLOY_DIR%"
                        )
                    )

                    REM Copy all files from workspace to local directory (excluding .git via a temporary exclude list)
                    echo .git> "%WORKSPACE%\\exclude.txt"
                    xcopy /E /Y /I "%WORKSPACE%\\*" "%LOCAL_DEPLOY_DIR%\\" /EXCLUDE:%WORKSPACE%\\exclude.txt
                    set XCOPY_STATUS=%ERRORLEVEL%
                    del "%WORKSPACE%\\exclude.txt"

                    if %XCOPY_STATUS% neq 0 (
                        echo ============================================
                        echo   ❌ Failed to copy files to local directory!
                        echo ============================================
                        exit /b %XCOPY_STATUS%
                    ) else (
                        echo ============================================
                        echo   ✅ Local directory updated successfully!
                        echo   Path: %LOCAL_DEPLOY_DIR%
                        echo ============================================
                    )
                '''
            }
        }
    }

    post {
        success {
            echo '''
            ============================================
              ✅ CD Pipeline completed successfully!
              - FTP deployment: DONE
              - Local directory update: DONE
            ============================================
            '''
        }
        failure {
            echo '''
            ============================================
              ❌ CD Pipeline FAILED!
              Check the logs above for errors.
            ============================================
            '''
        }
    }
}
