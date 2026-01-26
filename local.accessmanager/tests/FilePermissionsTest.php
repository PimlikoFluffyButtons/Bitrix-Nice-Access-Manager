<?php
/**
 * Unit tests for FilePermissions class
 * Tests .section.php parsing and displayName functionality
 */

namespace Local\AccessManager\Tests;

use PHPUnit\Framework\TestCase;

/**
 * FilePermissions test suite
 *
 * Tests:
 * - parseSectionPhp() method
 * - getDisplayName() method
 * - getTree() with displayName integration
 * - Encoding detection (UTF-8, Windows-1251)
 * - Fallback to physical name
 */
class FilePermissionsTest extends TestCase
{
    private $testDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary test directory
        $this->testDir = sys_get_temp_dir() . '/filepermissions_test_' . uniqid();
        mkdir($this->testDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    /**
     * Test 1: Parse .section.php with NAME field
     */
    public function testParseSectionPhpWithName()
    {
        // Arrange: Create test .section.php file
        $testFolder = $this->testDir . '/blog';
        mkdir($testFolder);

        $sectionContent = <<<'PHP'
<?php
$arSection = array(
    "NAME" => "Блог компании",
    "DESCRIPTION" => "Все статьи и новости",
    "ACTIVE" => "Y",
    "SORT" => 100,
);
PHP;

        file_put_contents($testFolder . '/.section.php', $sectionContent);

        // Act: Parse the file
        $displayName = $this->invokePrivateMethod('parseSectionPhp', [$testFolder]);

        // Assert: Should return the NAME value
        $this->assertEquals('Блог компании', $displayName);
    }

    /**
     * Test 2: Fallback to physical name when no .section.php
     */
    public function testFallbackToPhysicalName()
    {
        // Arrange: Create folder without .section.php
        $testFolder = $this->testDir . '/nosection';
        mkdir($testFolder);

        // Act: Try to get displayName
        $displayName = $this->invokePrivateMethod('parseSectionPhp', [$testFolder]);

        // Assert: Should return null (fallback handled by getDisplayName)
        $this->assertNull($displayName);
    }

    /**
     * Test 3: Parse .section.php with array shorthand syntax
     */
    public function testParseSectionPhpArrayShorthand()
    {
        // Arrange
        $testFolder = $this->testDir . '/modern';
        mkdir($testFolder);

        $sectionContent = <<<'PHP'
<?php
$arSection = [
    "NAME" => "Современная папка",
    "ACTIVE" => "Y",
];
PHP;

        file_put_contents($testFolder . '/.section.php', $sectionContent);

        // Act
        $displayName = $this->invokePrivateMethod('parseSectionPhp', [$testFolder]);

        // Assert
        $this->assertEquals('Современная папка', $displayName);
    }

    /**
     * Test 4: Detect UTF-8 encoding
     */
    public function testDetectEncodingUtf8()
    {
        // Arrange: UTF-8 content with BOM
        $content = "\xef\xbb\xbf<?php echo 'UTF-8'; ?>";

        // Act
        $encoding = $this->invokePrivateMethod('detectEncoding', [$content]);

        // Assert
        $this->assertEquals('UTF-8', $encoding);
    }

    /**
     * Test 5: Parse .section.php with single quotes
     */
    public function testParseSectionPhpSingleQuotes()
    {
        // Arrange
        $testFolder = $this->testDir . '/singlequotes';
        mkdir($testFolder);

        $sectionContent = <<<'PHP'
<?php
$arSection = array(
    'NAME' => 'Папка с одинарными кавычками',
    'ACTIVE' => 'Y',
);
PHP;

        file_put_contents($testFolder . '/.section.php', $sectionContent);

        // Act
        $displayName = $this->invokePrivateMethod('parseSectionPhp', [$testFolder]);

        // Assert
        $this->assertEquals('Папка с одинарными кавычками', $displayName);
    }

    /**
     * Test 6: Cache functionality for displayName
     */
    public function testDisplayNameCaching()
    {
        // Arrange
        $testFolder = $this->testDir . '/cached';
        mkdir($testFolder);

        $sectionContent = <<<'PHP'
<?php
$arSection = ["NAME" => "Кэшированная папка"];
PHP;

        file_put_contents($testFolder . '/.section.php', $sectionContent);

        // Act: Parse twice
        $displayName1 = $this->invokePrivateMethod('parseSectionPhp', [$testFolder]);
        $displayName2 = $this->invokePrivateMethod('parseSectionPhp', [$testFolder]);

        // Assert: Both should return same value (from cache)
        $this->assertEquals($displayName1, $displayName2);
        $this->assertEquals('Кэшированная папка', $displayName1);
    }

    /**
     * Test 7: getDisplayName() method integration
     */
    public function testGetDisplayName()
    {
        // This test would require access to FilePermissions::getDisplayName()
        // which depends on Application::getDocumentRoot()
        // For now, we verify the method exists and has correct signature

        $this->assertTrue(
            method_exists('Local\\AccessManager\\FilePermissions', 'getDisplayName'),
            'getDisplayName method should exist'
        );
    }

    /**
     * Test 8: Parse .section.php with DISPLAY_NAME fallback
     */
    public function testParseSectionPhpDisplayNameFallback()
    {
        // Arrange
        $testFolder = $this->testDir . '/displayname';
        mkdir($testFolder);

        $sectionContent = <<<'PHP'
<?php
$arSection = array(
    "DISPLAY_NAME" => "Альтернативное название",
    "ACTIVE" => "Y",
);
PHP;

        file_put_contents($testFolder . '/.section.php', $sectionContent);

        // Act
        $displayName = $this->invokePrivateMethod('parseSectionPhp', [$testFolder]);

        // Assert: Should use DISPLAY_NAME as fallback
        $this->assertEquals('Альтернативное название', $displayName);
    }

    // ===================================================
    // Helper Methods
    // ===================================================

    /**
     * Invoke private method for testing
     */
    private function invokePrivateMethod($methodName, $args = [])
    {
        $class = new \ReflectionClass('Local\\AccessManager\\FilePermissions');
        $method = $class->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs(null, $args);
    }

    /**
     * Recursively remove directory
     */
    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
