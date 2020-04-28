<?php


namespace ID\KeyManager\Repositories;


use ID\KeyManager\Factories\KeyFactory;
use Illuminate\Support\Arr;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class KeyRepository
{
    /**
     * @var \Illuminate\Config\Repository|\Illuminate\Contracts\Foundation\Application|mixed
     */
    private array $keys;

    /**
     * @var string
     */
    protected ?string $lastPrivate = null;

    /**
     * @var string
     */
    protected ?string $lastPublic = null;

    /**
     * @var bool
     */
    private bool $isDirty = false;

    /**
     * KeyRepository constructor.
     */
    public function __construct()
    {
        $this->keys = config('manager.credentials', []);
    }


    public function syncAll(): bool
    {
        foreach ($this->keys as $key => $value) {
            if (!$this->syncKey($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Sync the specific key.
     *
     * @param string $keyName
     *
     * @return bool
     */
    public function syncKey(string $keyName): bool
    {
        $this->validateKey($keyName);
        return $this->fetchKey($keyName);
    }

    /**
     * Retrieves a single key from the store.
     *
     * @param string       $keyName
     *
     * @param string|array $types
     *
     * @return bool
     */
    private function fetchKey(string $keyName, $types = '*'): bool
    {
        $key = $this->keys[$keyName];
        $sync = [];
        if (is_string($types) && $types === '*') {
            $sync = Arr::get($key, 'sync');
        } elseif (is_array($types)) {
            $sync = array_intersect(Arr::get($key, 'sync'), $types);
        }
        $key['sync'] = array_values($sync);
        $this->downloadKey($key);
        return true;
    }

    private function downloadKey(array $key): bool
    {
//        $factory = KeyFactory::generate($key);
//        dd($factory);
//        $directories = Storage::disk(config('manager.storage'))->directories();
//        dd($directories);
    }

    public function saveFromFactory(KeyFactory $factory): bool
    {
        if ($factory->isFinished()) {
            $this->lastPrivate = $factory->getPrivateKey();
            $this->lastPublic = $factory->getPublicKey();
            $this->isDirty = true;
            $this->saveKey($factory->getConfig());
        }
        return true;
    }

    /**
     * @param array $config
     *
     * @return bool
     */
    public function saveKey(array $config): bool
    {
        if ($this->isDirty === false) {
            return false;
        }

        $revision = call_user_func(KeyRepository::getRevision());
        $path = $this->getPath($config);
        $path = rtrim($path, '/') . '/' . $revision;
        $filename = '/' . Arr::get($config, 'filename');

        //  TODO: This should be replace with a quick validation of a key.
        if ($this->lastPublic !== null) {
            Storage::disk(config('manager.storage'))
                ->put("{$path}/{$filename}.pub", $this->lastPublic);
        }
        //  TODO: This should be replace with a quick validation of a key.
        if ($this->lastPrivate !== null) {
            Storage::disk(config('manager.storage'))
                ->put("{$path}/{$filename}.key", $this->lastPrivate);
        }

        $this->rotateKeys($config);
        return true;
    }

    /**
     * @param string $keyName
     *
     * @return bool
     */
    private function validateKey(string $keyName): bool
    {
        if (!key_exists($keyName, $this->keys)) {
            throw new \InvalidArgumentException(sprintf('The key "%s" is NOT defined.', $keyName));
        }
        return true;
    }

    /**
     * @return callable
     */
    public static function getRevision(): callable
    {
        return fn() => time();
    }

    /**
     * @param array|null $config
     *
     * @return bool
     */
    public function rotateKeys(?array $config = null): bool
    {
        try {
            if (!$config) {
                $rotateKeys = array_values($this->keys);
            } else {
                $rotateKeys = [$config];
            }
            foreach ($rotateKeys as $rotateKey) {
                $path = $this->getPath($rotateKey);
                $revisions = Storage::disk(config('manager.storage'))->directories($path);
                if (!is_array($revisions)) {
                    Log::info('There was no revision to rotate.');
                    return false;
                }
                $revisions = array_slice(
                    $revisions,
                    0,
                    count($revisions) - (int)Arr::get($config, 'keep', 1)
                );
                foreach ($revisions as $revision) {
                    $result = Storage::disk(config('manager.storage'))->deleteDirectory($revision);
                    if ($result === false) {
                        return false;
                    }
                }
            }
            return true;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    private function getPath(array $config): string
    {
        $path = config('manager.remote_path', '.');
        $path = rtrim($path, '/') . '/' . Arr::get($config, 'path');
        return rtrim($path, '/');
    }
}
