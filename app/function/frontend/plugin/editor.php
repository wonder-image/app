<?php

use Wonder\Plugin\Custom\Input\EditorBlocksRenderer;

function renderEditorBlocks(mixed $payload, array $config = []): string
{
    return EditorBlocksRenderer::make($payload, $config);
}

function editorBlocks(mixed $payload, array $config = []): string
{
    return renderEditorBlocks($payload, $config);
}
