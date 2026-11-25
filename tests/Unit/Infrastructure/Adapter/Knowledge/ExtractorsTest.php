<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Adapter\Knowledge;

use App\Application\Knowledge\Port\ExtractedPage;
use App\Infrastructure\Adapter\Knowledge\LocalFileStorage;
use App\Infrastructure\Adapter\Knowledge\MarkdownExtractor;
use App\Infrastructure\Adapter\Knowledge\PlainTextExtractor;
use PHPUnit\Framework\TestCase;

/** Lot L9 : extraction du texte et conservation des fichiers d'origine. */
final class ExtractorsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/hector-knowledge-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach ([$this->directory . '/storage', $this->directory] as $directory) {
            array_map(unlink(...), array_filter(glob($directory . '/*') ?: [], is_file(...)));
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function testMarkdownIsReadAsIsWithoutPages(): void
    {
        $extractor = new MarkdownExtractor();
        $path = $this->file('fiche.md', "\xEF\xBB\xBF# Titre\r\n\r\n| a | b |\r\n|---|---|\r\n");

        self::assertTrue($extractor->supports('Fiche.MD'));
        self::assertTrue($extractor->supports('notes.markdown'));
        self::assertFalse($extractor->supports('fiche.txt'));
        // Ni marque d'ordre des octets ni fins de ligne Windows : le tableau arrive intact
        self::assertEquals([new ExtractedPage(null, "# Titre\n\n| a | b |\n|---|---|\n")], $extractor->extract($path));
        self::assertSame('markdown@1', $extractor->name());
    }

    public function testPlainTextKeepsItsPageNumbers(): void
    {
        $extractor = new PlainTextExtractor();

        self::assertTrue($extractor->supports('norme.txt'));
        self::assertFalse($extractor->supports('norme.pdf'));
        // Une page vide garde son numéro : la suivante reste la quatrième
        self::assertEquals(
            [new ExtractedPage(1, 'Un.'), new ExtractedPage(2, 'Deux.'), new ExtractedPage(3, ''), new ExtractedPage(4, 'Quatre.')],
            $extractor->extract($this->file('pages.txt', "Un.\fDeux.\f\fQuatre.")),
        );
        self::assertEquals([new ExtractedPage(null, 'Sans saut de page.')], $extractor->extract($this->file('simple.txt', 'Sans saut de page.')));
    }

    public function testAFileThatIsNotUtf8IsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('UTF-8');

        (new MarkdownExtractor())->extract($this->file('latin1.md', "D\xE9clenchement"));
    }

    public function testAFileIsStoredOnceUnderItsFingerprint(): void
    {
        $storage = new LocalFileStorage($this->directory . '/storage');
        $path = $this->file('fiche.md', '# Titre');

        $stored = $storage->store($path);
        $again = $storage->store($this->file('copie.md', '# Titre'));

        self::assertSame(hash('sha256', '# Titre'), $stored->fingerprint);
        self::assertSame($stored->fingerprint . '.md', $stored->key);
        self::assertEquals($stored, $again);
        self::assertSame('# Titre', file_get_contents($storage->path($stored->key)));
        self::assertCount(1, glob($this->directory . '/storage/*') ?: []);
    }

    public function testAStorageKeyIsNeverAPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new LocalFileStorage($this->directory . '/storage'))->path('../../.env');
    }

    private function file(string $name, string $content): string
    {
        $path = $this->directory . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }
}
