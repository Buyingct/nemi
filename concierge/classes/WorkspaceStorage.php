<?php

declare(strict_types=1);

/**
 * Creates and manages the private storage folders for one workspace.
 */
final class WorkspaceStorage
{
    private string $storageRoot;

    public function __construct(?string $storageRoot = null)
    {
        $this->storageRoot = $storageRoot
            ?? dirname(__DIR__) . '/storage/workspaces';
    }

    /**
     * Returns the main storage folder for a workspace.
     */
    public function workspacePath(string $workspacePublicId): string
    {
        $workspacePublicId = $this->validatePublicId(
            $workspacePublicId
        );

        return $this->storageRoot . '/' . $workspacePublicId;
    }

    /**
     * Creates the complete folder structure for a workspace.
     *
     * @return array<string, string>
     */
    public function createWorkspaceFolders(
        string $workspacePublicId
    ): array {
        $workspacePath = $this->workspacePath(
            $workspacePublicId
        );

        $folders = [
            'workspace' => $workspacePath,
            'documents' => $workspacePath . '/documents',
            'extracted' => $workspacePath . '/extracted',
            'indexes' => $workspacePath . '/indexes',
            'thumbnails' => $workspacePath . '/thumbnails',
            'exports' => $workspacePath . '/exports',
            'temporary' => $workspacePath . '/temporary',
        ];

        foreach ($folders as $folderPath) {
            $this->createDirectory($folderPath);
        }

        return $folders;
    }

    /**
     * Returns the document folder for one workspace.
     */
    public function documentsPath(
        string $workspacePublicId
    ): string {
        $folders = $this->createWorkspaceFolders(
            $workspacePublicId
        );

        return $folders['documents'];
    }

    /**
     * Returns the extracted-text folder for one workspace.
     */
    public function extractedPath(
        string $workspacePublicId
    ): string {
        $folders = $this->createWorkspaceFolders(
            $workspacePublicId
        );

        return $folders['extracted'];
    }

    /**
     * Returns the local-search index folder for one workspace.
     */
    public function indexesPath(
        string $workspacePublicId
    ): string {
        $folders = $this->createWorkspaceFolders(
            $workspacePublicId
        );

        return $folders['indexes'];
    }

    private function createDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException(
                'The workspace storage folder could not be created.'
            );
        }
    }

    private function validatePublicId(
        string $workspacePublicId
    ): string {
        $workspacePublicId = strtolower(
            trim($workspacePublicId)
        );

        if (
            $workspacePublicId === ''
            || !preg_match(
                '/^[a-f0-9]{16}$/',
                $workspacePublicId
            )
        ) {
            throw new InvalidArgumentException(
                'The workspace identifier is invalid.'
            );
        }

        return $workspacePublicId;
    }
}