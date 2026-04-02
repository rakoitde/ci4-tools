#!/usr/bin/env php
<?php

declare(strict_types=1);

final class PatchException extends RuntimeException
{
}

final class Console
{
    public function __construct(
        private readonly bool $verbose = false
    ) {
    }

    public function writeln(string $message = ''): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    public function error(string $message): void
    {
        fwrite(STDERR, $message . PHP_EOL);
    }

    public function info(string $message): void
    {
        $this->writeln($message);
    }

    public function verbose(string $message): void
    {
        if ($this->verbose) {
            $this->writeln('[verbose] ' . $message);
        }
    }

    public function section(string $title): void
    {
        $this->writeln('************************************');
        $this->writeln('* ' . str_pad($title, 30, ' ', STR_PAD_BOTH) . ' *');
        $this->writeln('************************************');
        $this->writeln();
    }
}

final class PatchConfig
{
    public function __construct(
        public readonly string $projectRoot,
        public readonly string $scriptDirectory,
        public readonly string $currentVersion = '',
        public readonly string $targetVersion = '',
        public readonly string $gitUserEmail = '',
        public readonly string $gitUserName = '',
        public readonly bool $dryRun = false,
        public readonly bool $verbose = false,
        public readonly bool $resume = false,
        public readonly array $items = ['app', 'public', 'env', 'spark']
    ) {
    }

    public static function fromArgv(array $argv): self
    {
        $short = 'hc:v:e:n:';
        $long = [
            'help',
            'dry-run',
            'verbose',
            'resume',
        ];

        $options = getopt($short, $long);

        if (isset($options['h']) || isset($options['help'])) {
            self::showHelp($argv[0] ?? 'patch.php');
            exit(0);
        }

        $root = getcwd();
        if ($root === false) {
            throw new PatchException('Unable to determine current working directory.');
        }

        return new self(
            projectRoot: $root,
            scriptDirectory: __DIR__,
            currentVersion: (string) ($options['c'] ?? ''),
            targetVersion: (string) ($options['v'] ?? ''),
            gitUserEmail: (string) ($options['e'] ?? ''),
            gitUserName: (string) ($options['n'] ?? ''),
            dryRun: isset($options['dry-run']),
            verbose: isset($options['verbose']),
            resume: isset($options['resume'])
        );
    }

    public static function showHelp(string $scriptName): void
    {
        $help = <<<TXT

Usage:
  {$scriptName} [-c <current version>] [-v <target version>] [-e <git user.email>] [-n <git user.name>] [--dry-run] [--verbose] [--resume]

Patches an existing CodeIgniter 4 project repo to a different version of the framework.

Options:
  -h, --help     Show this help message and exit
  -c commit-ish  Alternate version to consider "current" (rarely needed)
  -v commit-ish  Version to use for patching. Defaults to the latest
  -e email       Git global user.email
  -n name        Git global user.name
  --dry-run      Show actions without making changes
  --verbose      Print executed commands and detailed diagnostics
  --resume       Resume from an interrupted patch run if recovery data exists

Examples:
  {$scriptName}
  {$scriptName} -v 4.6.1
  {$scriptName} --dry-run --verbose -v 4.6.1
  {$scriptName} --resume

TXT;

        fwrite(STDERR, $help . PHP_EOL);
    }
}

final class CommandResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr
    ) {
    }

    public function allOutput(): string
    {
        return trim($this->stdout . ($this->stderr !== '' ? PHP_EOL . $this->stderr : ''));
    }

    public function ensureSuccess(string $message): self
    {
        if ($this->exitCode !== 0) {
            $details = $this->allOutput();
            throw new PatchException($details !== '' ? $message . PHP_EOL . $details : $message);
        }

        return $this;
    }
}

final class CommandRunner
{
    public function __construct(
        private readonly string $workingDirectory,
        private readonly Console $console,
        private readonly bool $dryRun = false
    ) {
    }

    public function run(array $command, bool $printOutput = true): CommandResult
    {
        $this->console->verbose('CMD: ' . $this->formatCommand($command));

        if ($this->dryRun) {
            return new CommandResult(0, '', '');
        }

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $this->workingDirectory);

        if (!is_resource($process)) {
            throw new PatchException('Unable to start process: ' . $this->formatCommand($command));
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $stdout = $stdout === false ? '' : $stdout;
        $stderr = $stderr === false ? '' : $stderr;

        if ($printOutput) {
            if ($stdout !== '') {
                $this->console->writeln(rtrim($stdout, "\r\n"));
            }
            if ($stderr !== '') {
                $this->console->error(rtrim($stderr, "\r\n"));
            }
        }

        return new CommandResult($exitCode, trim($stdout), trim($stderr));
    }

    public function locateBinary(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            try {
                $result = $this->run([$candidate, '--version'], false);
                if ($result->exitCode === 0) {
                    return $candidate;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function formatCommand(array $command): string
    {
        return implode(' ', array_map(
            static fn(string $part): string => preg_match('/\s/', $part) ? '"' . $part . '"' : $part,
            $command
        ));
    }
}

final class FileSystem
{
    public function __construct(
        private readonly bool $dryRun = false,
        private readonly ?Console $console = null
    ) {
    }

    public function path(string ...$parts): string
    {
        $clean = [];

        foreach ($parts as $index => $part) {
            if ($part === '') {
                continue;
            }

            if ($index === 0) {
                $clean[] = rtrim($part, DIRECTORY_SEPARATOR . '/\\');
            } else {
                $clean[] = trim($part, DIRECTORY_SEPARATOR . '/\\');
            }
        }

        return implode(DIRECTORY_SEPARATOR, $clean);
    }

    public function exists(string $path): bool
    {
        return file_exists($path) || is_link($path);
    }

    public function isDir(string $path): bool
    {
        return is_dir($path);
    }

    public function readJsonFile(string $path): array
    {
        if (!$this->exists($path)) {
            throw new PatchException("JSON file not found: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new PatchException("Unable to read file: {$path}");
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new PatchException("Invalid JSON file: {$path}");
        }

        return $data;
    }

    public function writeJsonFile(string $path, array $data): void
    {
        $this->log("Write JSON {$path}");

        if ($this->dryRun) {
            return;
        }

        $dir = dirname($path);
        $this->ensureDir($dir);

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new PatchException("Unable to encode JSON for: {$path}");
        }

        if (file_put_contents($path, $json . PHP_EOL) === false) {
            throw new PatchException("Unable to write file: {$path}");
        }
    }

    public function ensureDir(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        $this->log("Create directory {$path}");

        if ($this->dryRun) {
            return;
        }

        if (!mkdir($path, 0777, true) && !is_dir($path)) {
            throw new PatchException("Unable to create directory: {$path}");
        }
    }

    public function delete(string $path): void
    {
        if (!$this->exists($path)) {
            return;
        }

        $this->log("Delete {$path}");

        if ($this->dryRun) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            if (!@unlink($path)) {
                throw new PatchException("Unable to delete file: {$path}");
            }

            return;
        }

        $items = scandir($path);
        if ($items === false) {
            throw new PatchException("Unable to read directory: {$path}");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $this->delete($this->path($path, $item));
        }

        if (!@rmdir($path)) {
            throw new PatchException("Unable to delete directory: {$path}");
        }
    }

    public function copy(string $source, string $destination): void
    {
        if (!$this->exists($source)) {
            throw new PatchException("Source does not exist: {$source}");
        }

        $this->log("Copy {$source} -> {$destination}");

        if ($this->dryRun) {
            return;
        }

        if (is_link($source)) {
            $target = readlink($source);
            if ($target === false) {
                throw new PatchException("Unable to read symlink: {$source}");
            }

            @unlink($destination);
            if (!@symlink($target, $destination)) {
                throw new PatchException("Unable to create symlink: {$destination}");
            }

            return;
        }

        if (is_file($source)) {
            $this->ensureDir(dirname($destination));

            if (!copy($source, $destination)) {
                throw new PatchException("Unable to copy file from {$source} to {$destination}");
            }

            return;
        }

        $this->ensureDir($destination);

        $items = scandir($source);
        if ($items === false) {
            throw new PatchException("Unable to read directory: {$source}");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $this->copy(
                $this->path($source, $item),
                $this->path($destination, $item)
            );
        }
    }

    public function copyIntoRoot(string $source, string $projectRoot): void
    {
        $this->copy($source, $this->path($projectRoot, basename($source)));
    }

    private function log(string $message): void
    {
        $this->console?->verbose($message);
    }
}

final class RecoveryStateStore
{
    private string $stateFile;

    public function __construct(
        private readonly FileSystem $fs,
        private readonly string $projectRoot
    ) {
        $this->stateFile = $this->fs->path($projectRoot, '.rakoitde-patch-state.json');
    }

    public function exists(): bool
    {
        return $this->fs->exists($this->stateFile);
    }

    public function load(): array
    {
        return $this->fs->readJsonFile($this->stateFile);
    }

    public function save(array $state): void
    {
        $this->fs->writeJsonFile($this->stateFile, $state);
    }

    public function delete(): void
    {
        if ($this->exists()) {
            $this->fs->delete($this->stateFile);
        }
    }

    public function getPath(): string
    {
        return $this->stateFile;
    }
}

final class GitRepository
{
    public function __construct(
        private readonly CommandRunner $runner,
        private readonly FileSystem $fs,
        private readonly string $root
    ) {
    }

    public function assertRepository(): void
    {
        if (!$this->fs->isDir($this->fs->path($this->root, '.git'))) {
            throw new PatchException("{$this->root} is not a valid git repository.");
        }
    }

    public function requireGitAvailable(): void
    {
        $this->runner->run(['git', '--version'], false)->ensureSuccess('Git must be installed.');
    }

    public function getCurrentBranch(): string
    {
        return trim(
            $this->runner
                ->run(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], false)
                ->ensureSuccess('Unable to determine current branch.')
                ->stdout
        );
    }

    public function getTopLevel(): string
    {
        return trim(
            $this->runner
                ->run(['git', 'rev-parse', '--show-toplevel'], false)
                ->ensureSuccess('Unable to determine repository root.')
                ->stdout
        );
    }

    public function assertCleanWorkingTree(string $branch): void
    {
        $status = trim(
            $this->runner
                ->run(['git', 'status', '--porcelain'], false)
                ->ensureSuccess('Unable to check git status.')
                ->stdout
        );

        if ($status !== '') {
            throw new PatchException(
                "You have unresolved issues in the current branch ({$branch}). Please resolve before patching."
            );
        }
    }

    public function configureGlobalUser(string $email, string $name): void
    {
        if ($email === '' || $name === '') {
            return;
        }

        $this->runner->run(['git', 'config', '--global', 'user.email', $email], false)
            ->ensureSuccess('Unable to set git user.email.');

        $this->runner->run(['git', 'config', '--global', 'user.name', $name], false)
            ->ensureSuccess('Unable to set git user.name.');
    }

    public function branchExists(string $branch): bool
    {
        $result = $this->runner->run(['git', 'rev-parse', '--verify', '--quiet', $branch], false);
        return $result->exitCode === 0;
    }

    public function hasUnmergedCommits(string $branch): bool
    {
        $result = $this->runner->run(['git', 'log', "HEAD..{$branch}"], false);
        return trim($result->stdout) !== '';
    }

    public function deleteBranch(string $branch, bool $force = false): void
    {
        $this->runner->run(['git', 'branch', $force ? '-D' : '-d', $branch], true)
            ->ensureSuccess("Unable to delete branch {$branch}");
    }

    public function checkoutOrphan(string $branch): void
    {
        $this->runner->run(['git', 'checkout', '--orphan', $branch], true)
            ->ensureSuccess("Unable to create orphan branch {$branch}.");
    }

    public function checkoutBranch(string $branch): void
    {
        $this->runner->run(['git', 'checkout', $branch], true)
            ->ensureSuccess("Unable to checkout branch {$branch}.");
    }

    public function removeAllTrackedFiles(): void
    {
        $result = $this->runner->run(['git', 'rm', '-rf', '.'], true);

        if ($result->exitCode !== 0) {
            $output = $result->allOutput();
            if (!str_contains($output, 'did not match any files')) {
                throw new PatchException('Unable to clear working tree.' . ($output !== '' ? PHP_EOL . $output : ''));
            }
        }
    }

    public function checkoutFilesFromBranch(string $branch, array $paths): void
    {
        $this->runner->run(['git', 'checkout', $branch, '--', ...$paths], true)
            ->ensureSuccess('Unable to restore files from base branch.');
    }

    public function cleanUntracked(): void
    {
        $this->runner->run(['git', 'clean', '-fd'], true)
            ->ensureSuccess('Unable to clean working tree.');
    }

    public function addAll(): void
    {
        $this->runner->run(['git', 'add', '.'], true)
            ->ensureSuccess('Unable to stage files.');
    }

    public function resetPaths(array $paths): void
    {
        $this->runner->run(['git', 'reset', '--', ...$paths], true)
            ->ensureSuccess('Unable to unstage files.');
    }

    public function restorePaths(array $paths): void
    {
        $this->runner->run(['git', 'restore', '--', ...$paths], true)
            ->ensureSuccess('Unable to restore files.');
    }

    public function commit(string $message): void
    {
        $this->runner->run(['git', 'commit', '-m', $message, '--no-verify'], false)
            ->ensureSuccess("Unable to commit: {$message}");
    }

    public function createBranchFrom(string $branch, string $base): void
    {
        $this->runner->run(['git', 'checkout', '-b', $branch, $base], true)
            ->ensureSuccess("Unable to create {$branch} branch.");
    }

    public function cherryPick(string $branch): CommandResult
    {
        return $this->runner->run(['git', 'cherry-pick', $branch], true);
    }

    public function abortCherryPickIfActive(): void
    {
        $gitDir = $this->fs->path($this->root, '.git');
        $cherryPickHead = $this->fs->path($gitDir, 'CHERRY_PICK_HEAD');

        if ($this->fs->exists($cherryPickHead)) {
            $this->runner->run(['git', 'cherry-pick', '--abort'], true)
                ->ensureSuccess('Unable to abort active cherry-pick.');
        }
    }

    public function status(): void
    {
        $this->runner->run(['git', 'status'], true)
            ->ensureSuccess('Unable to show git status.');
    }
}

final class ComposerLockReader
{
    public function __construct(
        private readonly FileSystem $fs,
        private readonly string $projectRoot
    ) {
    }

    public function detectInstalledCiVersion(string $package): string
    {
        $lockPath = $this->fs->path($this->projectRoot, 'composer.lock');
        $data = $this->fs->readJsonFile($lockPath);

        $packages = [];

        if (isset($data['packages']) && is_array($data['packages'])) {
            $packages = array_merge($packages, $data['packages']);
        }

        if (isset($data['packages-dev']) && is_array($data['packages-dev'])) {
            $packages = array_merge($packages, $data['packages-dev']);
        }

        foreach ($packages as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (($entry['name'] ?? null) === $package) {
                $version = (string) ($entry['version'] ?? '');
                if ($version !== '') {
                    return $version;
                }
            }
        }

        throw new PatchException("Unable to determine installed version of {$package} from composer.lock.");
    }
}

final class ComposerManager
{
    private readonly string $composerBinary;

    public function __construct(
        private readonly CommandRunner $runner
    ) {
        $binary = $this->runner->locateBinary(['composer', 'composer.phar', 'composer.bat']);
        if ($binary === null) {
            throw new PatchException('Composer must be installed and available in PATH.');
        }

        $this->composerBinary = $binary;
    }

    public function verifyAvailable(): void
    {
        $this->runner->run([$this->composerBinary, '--version'], false)
            ->ensureSuccess('Composer must be installed.');
    }

    public function requirePackage(string $package, string $version): CommandResult
    {
        return $this->runner->run([
            $this->composerBinary,
            'require',
            '--no-scripts',
            '--with-all-dependencies',
            $package,
            $version,
        ], true);
    }

    public function updatePackage(string $package): CommandResult
    {
        return $this->runner->run([
            $this->composerBinary,
            'update',
            '--no-scripts',
            '--with-all-dependencies',
            $package,
        ], true);
    }

    public function install(): CommandResult
    {
        return $this->runner->run([
            $this->composerBinary,
            'install',
            '--no-scripts',
        ], false);
    }
}

final class FrameworkPackageDetector
{
    public function __construct(
        private readonly FileSystem $fs,
        private readonly string $root
    ) {
    }

    public function detect(): string
    {
        if ($this->fs->isDir($this->fs->path($this->root, 'vendor', 'codeigniter4', 'framework'))) {
            return 'codeigniter4/framework';
        }

        if ($this->fs->isDir($this->fs->path($this->root, 'vendor', 'codeigniter4', 'codeigniter4'))) {
            return 'codeigniter4/codeigniter4';
        }

        throw new PatchException('Unable to locate a valid vendor path.');
    }
}

final class ProjectGuard
{
    public function __construct(
        private readonly FileSystem $fs,
        private readonly GitRepository $git,
        private readonly string $root
    ) {
    }

    public function assertValidPatchTarget(): void
    {
        $repoRoot = $this->git->getTopLevel();
        $normalizedRepoRoot = $this->normalizePath($repoRoot);
        $normalizedRoot = $this->normalizePath($this->root);

        if ($normalizedRepoRoot !== $normalizedRoot) {
            throw new PatchException(
                'Run this script from the repository root. ' .
                "Detected repository root: {$repoRoot}; current working directory: {$this->root}"
            );
        }

        $requiredFiles = [
            $this->fs->path($this->root, 'composer.json'),
            $this->fs->path($this->root, 'composer.lock'),
            $this->fs->path($this->root, '.gitignore'),
        ];

        foreach ($requiredFiles as $file) {
            if (!$this->fs->exists($file)) {
                throw new PatchException("Required file missing: {$file}");
            }
        }

        $hasApp = $this->fs->exists($this->fs->path($this->root, 'app'));
        $hasPublic = $this->fs->exists($this->fs->path($this->root, 'public'));
        $hasSpark = $this->fs->exists($this->fs->path($this->root, 'spark'));

        if (!$hasApp || !$hasPublic || !$hasSpark) {
            throw new PatchException(
                'This does not look like a standard CodeIgniter project root. ' .
                'Expected at least app/, public/, and spark.'
            );
        }
    }

    private function normalizePath(string $path): string
    {
        $real = realpath($path);
        $normalized = $real !== false ? $real : $path;
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $normalized);

        if (DIRECTORY_SEPARATOR === '\\') {
            $normalized = strtolower($normalized);
        }

        return rtrim($normalized, DIRECTORY_SEPARATOR);
    }
}

final class PatchVersionParser
{
    public function extractFromTo(string $output, string $package): string
    {
        $pattern = '/' . preg_quote($package, '/') . '.*\((v?[A-Za-z0-9.\-_]+ => v?[A-Za-z0-9.\-_]+)\)/i';

        if (preg_match($pattern, $output, $matches) === 1) {
            return '(' . $matches[1] . ')';
        }

        return '';
    }
}

final class Patcher
{
    private string $baseBranch = '';
    private string $package = '';
    private string $detectedCurrentVersion = '';

    public function __construct(
        private readonly PatchConfig $config,
        private readonly Console $console,
        private readonly FileSystem $fs,
        private readonly GitRepository $git,
        private readonly ComposerManager $composer,
        private readonly FrameworkPackageDetector $packageDetector,
        private readonly ComposerLockReader $lockReader,
        private readonly ProjectGuard $projectGuard,
        private readonly PatchVersionParser $versionParser,
        private readonly RecoveryStateStore $stateStore
    ) {
    }

    public function run(): int
    {
        if ($this->config->resume) {
            return $this->resume();
        }

        $this->bootstrap();
        $this->showConfiguration();
        $this->stageFramework();
        return $this->mergePatch();
    }

    private function bootstrap(): void
    {
        $this->git->requireGitAvailable();
        $this->composer->verifyAvailable();
        $this->git->configureGlobalUser(
            $this->config->gitUserEmail,
            $this->config->gitUserName
        );

        $this->git->assertRepository();
        $this->projectGuard->assertValidPatchTarget();

        $this->baseBranch = $this->git->getCurrentBranch();
        $this->git->assertCleanWorkingTree($this->baseBranch);
        $this->package = $this->packageDetector->detect();
        $this->detectedCurrentVersion = $this->lockReader->detectInstalledCiVersion($this->package);

        if ($this->stateStore->exists()) {
            throw new PatchException(
                'A recovery state already exists at ' . $this->stateStore->getPath() . PHP_EOL .
                'Use --resume or delete the state file after reviewing the repository state.'
            );
        }

        foreach (['rakoitde/scratch', 'rakoitde/patches'] as $branch) {
            if (!$this->git->branchExists($branch)) {
                continue;
            }

            if ($this->git->hasUnmergedCommits($branch)) {
                throw new PatchException("Unmerged commits on {$branch}");
            }

            $this->git->deleteBranch($branch);
        }
    }

    private function showConfiguration(): void
    {
        $this->console->section('CONFIGURATION');
        $this->console->writeln("Scripts Directory:       {$this->config->scriptDirectory}");
        $this->console->writeln("Project Directory:       {$this->config->projectRoot}");
        $this->console->writeln("Dry Run:                 " . ($this->config->dryRun ? 'yes' : 'no'));
        $this->console->writeln("Verbose:                 " . ($this->config->verbose ? 'yes' : 'no'));
        $this->console->writeln("Target Version:          {$this->config->targetVersion}");
        $this->console->writeln("Current Version (arg):   {$this->config->currentVersion}");
        $this->console->writeln("Current Version (lock):  {$this->detectedCurrentVersion}");
        $this->console->writeln("Source Package:          {$this->package}");
        $this->console->writeln("Base Branch:             {$this->baseBranch}");
        $this->console->writeln("Selected Items:          " . implode(' ', $this->config->items));
        $this->console->writeln();
    }

    private function stageFramework(): void
    {
        $this->console->section('STAGING');

        $this->saveState('bootstrap-complete', [
            'baseBranch' => $this->baseBranch,
            'package' => $this->package,
            'detectedCurrentVersion' => $this->detectedCurrentVersion,
        ]);

        $this->git->checkoutOrphan('rakoitde/scratch');
        $this->saveState('scratch-created');

        $this->git->removeAllTrackedFiles();
        $this->deleteProjectItems();

        $this->git->checkoutFilesFromBranch($this->baseBranch, ['.gitignore', 'composer.json', 'composer.lock']);
        $this->git->cleanUntracked();
        $this->saveState('base-files-restored');

        if ($this->config->currentVersion !== '') {
            $this->composer->requirePackage($this->package, $this->config->currentVersion)
                ->ensureSuccess('Unable to require the requested current framework version.');

            $this->git->restorePaths(['composer.json', 'composer.lock']);
        }

        $this->copyFrameworkItemsFromVendor();
        $this->git->addAll();
        $this->git->resetPaths(['composer.json', 'composer.lock']);
        $this->git->commit('Stage framework');
        $this->saveState('scratch-staged');

        $fromTo = $this->updateFrameworkVersion();
        $this->replaceProjectItemsWithPatchedVendorItems();
        $this->git->addAll();
        $this->git->resetPaths(['composer.json', 'composer.lock']);
        $this->git->commit('Patch framework' . ($fromTo !== '' ? ' ' . $fromTo : ''));
        $this->saveState('scratch-patched', ['fromTo' => $fromTo]);

        $this->removeComposerFiles();
        $this->git->createBranchFrom('rakoitde/patches', $this->baseBranch);
        $this->saveState('patch-branch-created');

        $this->composer->install()->ensureSuccess('Unable to restore original vendor state.');
        $this->saveState('vendor-restored');
    }

    private function mergePatch(): int
    {
        $this->console->section('MERGING');

        $result = $this->git->cherryPick('rakoitde/scratch');

        if ($result->exitCode === 0) {
            $this->console->section('SUCCESS');
            $this->console->writeln('Patch successful! Updated files are available on branch rakoitde/patches.');
            $this->git->deleteBranch('rakoitde/scratch', true);
            $this->stateStore->delete();
            return 0;
        }

        $this->saveState('merge-conflict');
        $this->git->status();

        $this->console->writeln();
        $this->console->section('RESOLUTION');
        $this->console->writeln('Conflicts detected during patch! Follow the git instructions for resolution.');
        $this->console->writeln('Once resolution is complete your changes will be available on branch rakoitde/patches');
        $this->console->writeln('and you should remove the old working branch at rakoitde/scratch.');
        $this->console->writeln();
        $this->console->writeln('After manual cleanup you can remove the recovery file: ' . $this->stateStore->getPath());

        return 1;
    }

    private function resume(): int
    {
        if (!$this->stateStore->exists()) {
            throw new PatchException('No recovery state found.');
        }

        $state = $this->stateStore->load();

        $this->console->section('RECOVERY');
        $this->console->writeln('Recovery file: ' . $this->stateStore->getPath());
        $this->console->writeln('Last phase:    ' . (string) ($state['phase'] ?? 'unknown'));
        $this->console->writeln();

        $phase = (string) ($state['phase'] ?? '');

        $this->git->requireGitAvailable();
        $this->composer->verifyAvailable();
        $this->git->assertRepository();

        if ($phase === 'merge-conflict') {
            $this->console->writeln('A cherry-pick conflict is still pending or was previously recorded.');
            $this->git->status();
            return 1;
        }

        if ($phase === 'vendor-restored' || $phase === 'patch-branch-created') {
            $this->console->writeln('Attempting to resume merge phase.');
            $this->baseBranch = (string) ($state['baseBranch'] ?? $this->git->getCurrentBranch());
            $this->package = (string) ($state['package'] ?? '');
            return $this->mergePatch();
        }

        $this->console->writeln('Automatic recovery to a clean base branch.');
        $this->restoreToBaseFromState($state);
        $this->stateStore->delete();
        $this->console->writeln('Repository restored. Re-run the patch command normally.');
        return 0;
    }

    private function restoreToBaseFromState(array $state): void
    {
        $baseBranch = (string) ($state['baseBranch'] ?? '');
        if ($baseBranch === '') {
            throw new PatchException('Recovery state does not contain baseBranch.');
        }

        $this->git->abortCherryPickIfActive();

        if ($this->git->branchExists($baseBranch)) {
            $this->git->checkoutBranch($baseBranch);
        }

        foreach (['rakoitde/patches', 'rakoitde/scratch'] as $branch) {
            if ($this->git->branchExists($branch) && !$this->git->hasUnmergedCommits($branch)) {
                $this->git->deleteBranch($branch, true);
            }
        }

        $this->git->cleanUntracked();
    }

    private function saveState(string $phase, array $extra = []): void
    {
        $state = array_merge([
            'phase' => $phase,
            'baseBranch' => $this->baseBranch,
            'package' => $this->package,
            'timestamp' => date(DATE_ATOM),
        ], $extra);

        $this->stateStore->save($state);
    }

    private function deleteProjectItems(): void
    {
        foreach ($this->config->items as $item) {
            $path = $this->fs->path($this->config->projectRoot, $item);
            if ($this->fs->exists($path)) {
                $this->fs->delete($path);
            }
        }
    }

    private function copyFrameworkItemsFromVendor(): void
    {
        foreach ($this->config->items as $item) {
            $source = $this->fs->path(
                $this->config->projectRoot,
                'vendor',
                ...explode('/', $this->package),
                $item
            );

            if (!$this->fs->exists($source)) {
                throw new PatchException("Unable to locate framework item in vendor: {$source}");
            }

            $this->fs->copyIntoRoot($source, $this->config->projectRoot);
        }
    }

    private function updateFrameworkVersion(): string
    {
        if ($this->config->targetVersion !== '') {
            $result = $this->composer->requirePackage($this->package, $this->config->targetVersion);
            $result->ensureSuccess('Unable to require target framework version.');
        } else {
            $result = $this->composer->updatePackage($this->package);
            $result->ensureSuccess('Unable to update framework to latest version.');
        }

        return $this->versionParser->extractFromTo($result->allOutput(), $this->package);
    }

    private function replaceProjectItemsWithPatchedVendorItems(): void
    {
        foreach ($this->config->items as $item) {
            $projectPath = $this->fs->path($this->config->projectRoot, $item);
            if ($this->fs->exists($projectPath)) {
                $this->fs->delete($projectPath);
            }
        }

        $this->copyFrameworkItemsFromVendor();
    }

    private function removeComposerFiles(): void
    {
        foreach (['composer.json', 'composer.lock'] as $file) {
            $path = $this->fs->path($this->config->projectRoot, $file);
            if ($this->fs->exists($path)) {
                $this->fs->delete($path);
            }
        }
    }
}

final class Application
{
    public function run(array $argv): int
    {
        $config = PatchConfig::fromArgv($argv);
        $console = new Console($config->verbose);

        try {
            $fs = new FileSystem($config->dryRun, $console);
            $runner = new CommandRunner($config->projectRoot, $console, $config->dryRun);
            $git = new GitRepository($runner, $fs, $config->projectRoot);
            $composer = new ComposerManager($runner);
            $detector = new FrameworkPackageDetector($fs, $config->projectRoot);
            $lockReader = new ComposerLockReader($fs, $config->projectRoot);
            $guard = new ProjectGuard($fs, $git, $config->projectRoot);
            $parser = new PatchVersionParser();
            $stateStore = new RecoveryStateStore($fs, $config->projectRoot);

            $patcher = new Patcher(
                $config,
                $console,
                $fs,
                $git,
                $composer,
                $detector,
                $lockReader,
                $guard,
                $parser,
                $stateStore
            );

            return $patcher->run();
        } catch (PatchException $e) {
            $console->error('ERROR: ' . $e->getMessage());
            return 1;
        } catch (Throwable $e) {
            $console->error('UNEXPECTED ERROR: ' . $e->getMessage());
            return 1;
        }
    }
}

exit((new Application())->run($argv));