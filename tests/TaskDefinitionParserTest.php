<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleTask\Tests;

use AaiEduHr\HeartPhrameModuleTask\Service\TaskDefinition;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskDefinitionParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaskDefinitionParser::class)]
#[CoversClass(TaskDefinition::class)]
final class TaskDefinitionParserTest extends TestCase
{
    /**
     * HR: Provjerava čitanje stabilnih UUID-eva, teksta i viewers opsega iz HTML-a.
     * EN: Verifies stable UUID, text, and viewer-scope parsing from HTML.
     */
    public function testParsesValidTaskDefinitions(): void
    {
        $html = <<<'HTML'
<section data-editor-html-task-list="1"
         data-task-list-uuid="5adf2862-a532-4d66-b916-b977284fc159"
         data-task-toggle-scope="viewers">
    <h3>Zadaci</h3>
    <ul>
        <li data-task-uuid="e3d1be37-04fd-4f69-ad6e-39fa9f085223">Prvi zadatak</li>
        <li data-task-uuid="a03d850b-2d60-491c-a659-ed4a3fbc646c">Drugi zadatak</li>
    </ul>
</section>
HTML;
        $definitions = (new TaskDefinitionParser())->parse($html);

        $this->assertCount(2, $definitions);
        $this->assertSame('Prvi zadatak', $definitions[0]->text);
        $this->assertSame('viewers', $definitions[0]->scope);
        $this->assertSame(
            'a03d850b-2d60-491c-a659-ed4a3fbc646c',
            $definitions[1]->uuid,
        );
    }

    /**
     * HR: Nevaljani UUID i prazan tekst ne smiju postati operativni zadaci.
     * EN: Invalid UUIDs and empty text must not become operational tasks.
     */
    public function testRejectsMalformedDefinitions(): void
    {
        $html = <<<'HTML'
<section data-editor-html-task-list="1" data-task-list-uuid="not-a-uuid">
    <ul><li data-task-uuid="also-invalid">Tekst</li></ul>
</section>
HTML;

        $this->assertSame([], (new TaskDefinitionParser())->parse($html));
    }
}
