<?php

/*
 --------------------------------------------------------------------
                   The PHP License, version 3.01
 Copyright (c) 1999 - 2010 The PHP Group. All rights reserved.
 --------------------------------------------------------------------

 Redistribution and use in source and binary forms, with or without
 modification, is permitted provided that the following conditions
 are met:

   1. Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.

  2. Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in
      the documentation and/or other materials provided with the
      distribution.

   3. The name "PHP" must not be used to endorse or promote products
      derived from this software without prior written permission. For
      written permission, please contact group@php.net.

   4. Products derived from this software may not be called "PHP", nor
      may "PHP" appear in their name, without prior written permission
      from group@php.net.  You may indicate that your software works in
      conjunction with PHP by saying "Foo for PHP" instead of calling
      it "PHP Foo" or "phpfoo"

   5. The PHP Group may publish revised and/or new versions of the
      license from time to time. Each version will be given a
      distinguishing version number.
      Once covered code has been published under a particular version
      of the license, you may always continue to use it under the terms
      of that version. You may also choose to use such covered code
      under the terms of any subsequent version of the license
      published by the PHP Group. No one other than the PHP Group has
      the right to modify the terms applicable to covered code created
      under this License.

   6. Redistributions of any form whatsoever must retain the following
      acknowledgment:
      "This product includes PHP software, freely available from
      <http://www.php.net/software/>".

 THIS SOFTWARE IS PROVIDED BY THE PHP DEVELOPMENT TEAM ``AS IS'' AND
 ANY EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO,
 THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A
 PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL THE PHP
 DEVELOPMENT TEAM OR ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
 INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 OF THE POSSIBILITY OF SUCH DAMAGE.

 --------------------------------------------------------------------

 This software consists of voluntary contributions made by many
 individuals on behalf of the PHP Group.

 The PHP Group can be contacted via Email at group@php.net.

 For more information on the PHP Group and the PHP project,
 please see <http://www.php.net>.

 PHP includes the Zend Engine, freely available at
 <http://www.zend.com>.
*/

/**
 * Extension build script for pre-PIE distribution
 */

class ExtensionBuilder
{
    private $extensionName;
    private $packageDir;
    private $platform;
    private $phpVersion;

    public function __construct()
    {
        $this->packageDir = dirname(__DIR__);
        $this->extensionName = 'valkey-glide';
        $this->platform = $this->detectPlatform();
        $this->phpVersion = PHP_VERSION;

        echo "Building {$this->extensionName} extension for {$this->platform} (PHP {$this->phpVersion})...\n";
    }

    public function build()
    {
        // Check if extension is already loaded
        if (extension_loaded($this->extensionName)) {
            echo "Extension {$this->extensionName} is already loaded.\n";
            return;
        }

        // Platform-specific build process
        switch ($this->platform) {
            case 'darwin':
                $this->buildMacOS();
                break;
            case 'linux':
                $this->buildLinux();
                break;
            case 'windows':
                $this->buildWindows();
                break;
            default:
                throw new Exception("Unsupported platform: {$this->platform}");
        }

        echo "Extension {$this->extensionName} built and installed successfully!\n";
        $this->showPostInstallInstructions();
    }

    private function detectPlatform()
    {
        $os = strtolower(PHP_OS_FAMILY);

        switch ($os) {
            case 'darwin':
                return 'darwin';
            case 'linux':
                return 'linux';
            case 'windows':
                return 'windows';
            default:
                // Try to detect Unix-like systems
                if (stripos(PHP_OS, 'bsd') !== false) {
                    return 'linux'; // Treat BSD like Linux
                }
                return $os;
        }
    }

    private function buildMacOS()
    {
        echo "Building for macOS...\n";

        // Check for required tools
        $this->checkMacOSBuildTools();

        // macOS-specific build commands
        $commands = [
            'python3 utils/remove_optional_from_proto.py',
            'cd valkey-glide/ffi',
            'cargo build --release',
            'cd ../../',
            'phpize',
            './configure --enable-' . $this->extensionName,
            'make clean',
            'make'
        ];

        $this->runCommands($commands);
        $this->installExtensionMacOS();
    }

    private function buildLinux()
    {
        echo "Building for Linux...\n";

        // Check for required tools
        $this->checkLinuxBuildTools();

        $commands = [
            'python3 utils/remove_optional_from_proto.py',
            'cd valkey-glide/ffi',
            'cargo build --release',
            'cd ../../',
            'phpize',
            './configure --enable-' . $this->extensionName,
            'make clean',
            'make'
        ];

        $this->runCommands($commands);
        $this->installExtensionLinux();
    }

    private function buildWindows()
    {
        echo "Building for Windows...\n";

        // Check for required tools
        $this->checkWindowsBuildTools();

        $commands = [
            'phpize',
            'configure --enable-' . $this->extensionName,
            'nmake clean',
            'nmake'
        ];

        $this->runCommands($commands);
        $this->installExtensionWindows();
    }

    private function checkMacOSBuildTools()
    {
        $tools = [
            'python3' => 'Python',
            'cargo' => 'Cargo (Rust build tools)',
            'phpize' => 'PHP development tools (install with: brew install php)',
            'php-config' => 'PHP configuration tool',
            'make' => 'Build tools (install Xcode command line tools)',
            'clang' => 'C compiler (install Xcode command line tools)'
        ];

        foreach ($tools as $tool => $description) {
            if (!$this->commandExists($tool)) {
                throw new Exception("Required tool '$tool' not found. $description");
            }
        }

        // Check for Xcode command line tools
        if (!is_dir('/Library/Developer/CommandLineTools') && !is_dir('/Applications/Xcode.app')) {
            echo "Warning: Xcode command line tools may not be installed.\n";
            echo "Run: xcode-select --install\n";
        }

        // Check for Homebrew PHP (common on macOS)
        $phpConfigPath = trim(shell_exec('which php-config'));
        if (strpos($phpConfigPath, '/opt/homebrew') !== false || strpos($phpConfigPath, '/usr/local') !== false) {
            echo "Detected Homebrew PHP installation: $phpConfigPath\n";
        }
    }

    private function checkLinuxBuildTools()
    {
        $tools = [
            'python3' => 'Python',
            'cargo' => 'Cargo (Rust build tools)',
            'phpize' => 'PHP development package (install with: apt-get install php-dev or yum install php-devel)',
            'php-config' => 'PHP configuration tool',
            'make' => 'Build tools (install with: apt-get install build-essential)',
            'gcc' => 'C compiler'
        ];

        foreach ($tools as $tool => $description) {
            if (!$this->commandExists($tool)) {
                throw new Exception("Required tool '$tool' not found. $description");
            }
        }
    }

    private function checkWindowsBuildTools()
    {
        $tools = [
            'phpize' => 'PHP development tools',
            'nmake' => 'Microsoft build tools (Visual Studio required)'
        ];

        foreach ($tools as $tool => $description) {
            if (!$this->commandExists($tool)) {
                throw new Exception("Required tool '$tool' not found. $description");
            }
        }
    }

    private function commandExists($command)
    {
        $whereIsCommand = $this->platform === 'windows' ? 'where' : 'which';
        $output = shell_exec("$whereIsCommand $command 2>/dev/null");
        return !empty($output);
    }

    private function runCommands($commands)
    {
        foreach ($commands as $command) {
            echo "Running: $command\n";
            $output = [];
            $returnCode = 0;
            exec("cd {$this->packageDir} && $command 2>&1", $output, $returnCode);

            if ($returnCode !== 0) {
                echo "Command failed: $command\n";
                echo implode("\n", $output) . "\n";
                throw new Exception("Build failed at: $command");
            }

            // Show last few lines of output for progress
            $outputLines = array_slice($output, -3);
            foreach ($outputLines as $line) {
                if (trim($line)) {
                    echo "  $line\n";
                }
            }
        }
    }

    private function installExtensionMacOS()
    {
        $extensionFile = $this->extensionName . '.so';
        $builtExtension = $this->packageDir . '/modules/' . $extensionFile;

        if (!file_exists($builtExtension)) {
            throw new Exception("Built extension not found: $builtExtension");
        }

        // Get PHP extension directory
        $extDir = trim(shell_exec('php-config --extension-dir'));
        if (!$extDir) {
            $extDir = ini_get('extension_dir') ?: PHP_EXTENSION_DIR;
        }

        $targetPath = $extDir . '/' . $extensionFile;

        // Try to install to system directory
        if (!@copy($builtExtension, $targetPath)) {
            // Fall back to user directory
            $userExtDir = $this->getUserExtensionDir();
            if (!is_dir($userExtDir)) {
                mkdir($userExtDir, 0755, true);
            }

            $targetPath = $userExtDir . '/' . $extensionFile;
            if (!copy($builtExtension, $targetPath)) {
                throw new Exception("Failed to install extension");
            }

            echo "Extension installed to user directory: $targetPath\n";
            echo "Add 'extension_dir=\"$userExtDir\"' to your php.ini\n";
        } else {
            echo "Extension installed to: $targetPath\n";
        }

        // macOS-specific: Check for SIP (System Integrity Protection) issues
        if (strpos($extDir, '/usr/lib') === 0) {
            echo "Note: macOS System Integrity Protection may prevent installation to system directories.\n";
            echo "Consider using Homebrew PHP or installing to user directory.\n";
        }
    }

    private function installExtensionLinux()
    {
        $extensionFile = $this->extensionName . '.so';
        $builtExtension = $this->packageDir . '/modules/' . $extensionFile;

        if (!file_exists($builtExtension)) {
            throw new Exception("Built extension not found: $builtExtension");
        }

        $extDir = ini_get('extension_dir') ?: PHP_EXTENSION_DIR;
        $targetPath = $extDir . '/' . $extensionFile;

        if (!@copy($builtExtension, $targetPath)) {
            // Try with sudo
            $sudoCommand = "sudo cp $builtExtension $targetPath";
            echo "Trying with sudo: $sudoCommand\n";
            $output = [];
            $returnCode = 0;
            exec($sudoCommand, $output, $returnCode);

            if ($returnCode !== 0) {
                // Fall back to user directory
                $userExtDir = $this->getUserExtensionDir();
                if (!is_dir($userExtDir)) {
                    mkdir($userExtDir, 0755, true);
                }

                $targetPath = $userExtDir . '/' . $extensionFile;
                if (!copy($builtExtension, $targetPath)) {
                    throw new Exception("Failed to install extension");
                }

                echo "Extension installed to user directory: $targetPath\n";
                echo "Add 'extension_dir=\"$userExtDir\"' to your php.ini\n";
            } else {
                echo "Extension installed to: $targetPath\n";
            }
        } else {
            echo "Extension installed to: $targetPath\n";
        }
    }

    private function installExtensionWindows()
    {
        $extensionFile = $this->extensionName . '.dll';
        $builtExtension = $this->packageDir . '/Release/' . $extensionFile;

        // Windows might have different build output locations
        if (!file_exists($builtExtension)) {
            $builtExtension = $this->packageDir . '/modules/' . $extensionFile;
        }

        if (!file_exists($builtExtension)) {
            throw new Exception("Built extension not found: $builtExtension");
        }

        $extDir = ini_get('extension_dir') ?: PHP_EXTENSION_DIR;
        $targetPath = $extDir . '\\' . $extensionFile;

        if (!copy($builtExtension, $targetPath)) {
            throw new Exception("Failed to install extension to: $targetPath");
        }

        echo "Extension installed to: $targetPath\n";
    }

    private function getUserExtensionDir()
    {
        if ($this->platform === 'windows') {
            $home = $_SERVER['USERPROFILE'] ?? 'C:\\temp';
            return $home . '\\.php\\extensions';
        } else {
            $home = $_SERVER['HOME'] ?? '/tmp';
            return $home . '/.php/extensions';
        }
    }

    private function showPostInstallInstructions()
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "POST-INSTALLATION INSTRUCTIONS\n";
        echo str_repeat("=", 60) . "\n";

        echo "1. Add this line to your php.ini file:\n";
        echo "   extension={$this->extensionName}\n\n";

        echo "2. Find your php.ini location:\n";
        echo "   php --ini\n\n";

        echo "3. Restart your web server (if applicable):\n";
        switch ($this->platform) {
            case 'darwin':
                echo "   # For Apache (Homebrew):\n";
                echo "   brew services restart httpd\n";
                echo "   # For Nginx (Homebrew):\n";
                echo "   brew services restart nginx\n";
                echo "   # For built-in server:\n";
                echo "   # Just restart your PHP process\n";
                break;
            case 'linux':
                echo "   sudo systemctl restart apache2  # or nginx, php-fpm\n";
                break;
            case 'windows':
                echo "   # Restart IIS or your web server\n";
                break;
        }

        echo "\n4. Verify installation:\n";
        echo "   php -m | grep {$this->extensionName}\n";
        echo "   php -r \"var_dump(extension_loaded('{$this->extensionName}'));\"\n\n";

        if ($this->platform === 'darwin') {
            echo "macOS-specific notes:\n";
            echo "- If using Homebrew PHP, make sure you're using the correct PHP binary\n";
            echo "- Check: which php (should show /opt/homebrew/bin/php or /usr/local/bin/php)\n";
            echo "- Multiple PHP versions: Use php@8.1, php@8.2, etc.\n\n";
        }

        echo "For troubleshooting, visit:\n";
        echo "https://github.com/yourcompany/your-extension/issues\n";
        echo str_repeat("=", 60) . "\n";
    }
}

// Run the builder
try {
    $builder = new ExtensionBuilder();
    $builder->build();
} catch (Exception $e) {
    echo "Build failed: " . $e->getMessage() . "\n";

    // Platform-specific troubleshooting hints
    $platform = strtolower(PHP_OS_FAMILY);
    echo "\nTroubleshooting hints for $platform:\n";

    switch ($platform) {
        case 'darwin':
            echo "- Install Xcode command line tools: xcode-select --install\n";
            echo "- Install Homebrew PHP: brew install php\n";
            echo "- Check PHP version: php --version\n";
            echo "- Ensure php-config is in PATH: which php-config\n";
            break;
        case 'linux':
            echo "- Install PHP dev package: sudo apt-get install php-dev (Ubuntu/Debian)\n";
            echo "- Or: sudo yum install php-devel (CentOS/RHEL)\n";
            echo "- Install build tools: sudo apt-get install build-essential\n";
            break;
        case 'windows':
            echo "- Install Visual Studio with C++ support\n";
            echo "- Use PHP SDK for Windows\n";
            echo "- Ensure nmake is in PATH\n";
            break;
    }

    exit(1);
}
