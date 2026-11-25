<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class FileUploadComponentTest extends TestCase
{
    public function test_file_upload_component_keeps_the_file_input_clickable_and_preserves_its_attributes(): void
    {
        $html = Blade::render(
            '<x-file-upload id="document-file" name="document" accept=".pdf,application/pdf" max-bytes="10485760" required multiple />',
        );
        $labelStart = strpos($html, 'data-file-dropzone-target');
        $inputPosition = strpos($html, 'name="document"');
        $labelEnd = strpos($html, '</label>', $labelStart);

        $this->assertNotFalse($labelStart);
        $this->assertNotFalse($inputPosition);
        $this->assertNotFalse($labelEnd);
        $this->assertLessThan($inputPosition, $labelStart);
        $this->assertLessThan($labelEnd, $inputPosition);
        $this->assertStringContainsString('id="document-file"', $html);
        $this->assertStringContainsString('accept=".pdf,application/pdf"', $html);
        $this->assertStringContainsString('required', $html);
        $this->assertStringContainsString('multiple', $html);
        $this->assertStringContainsString('data-max-bytes="10485760"', $html);
        $this->assertStringContainsString('Glissez-déposez votre fichier ici', $html);
        $this->assertStringContainsString('data-file-upload-progress-state hidden', $html);
        $this->assertStringContainsString('<progress data-file-upload-progress max="100"', $html);
        $this->assertStringNotContainsString('data-file-upload-progress max="100" value=', $html);
    }
}
