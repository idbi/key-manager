<?php


namespace ID\KeyManager\Repositories;

use ID\KeyManager\Factories\KeyFactory;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KeyRepository
{
    /**
     * @var Repository|Application|mixed
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
     * @var bool
     */
    protected bool $isStrict = false;

    /**
     * KeyRepository constructor.
     */
    public function __construct()
    {
        $this->keys = config('manager.credentials', []);
        $this->isStrict = config('manager.strict', false);
    }


    public function syncAll(): bool
    {
        foreach ($this->keys as $key => $value) {
            if (! $this->syncKey($key)) {
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
     * @param string $keyName
     *
     * @param string|array $types
     *
     * @return bool
     */
    private function fetchKey(string $keyName, $types = '*'): bool
    {
        $key = $this->keys[$keyName];
        $sync = [];
        if ($types === '*') {
            $sync = ['*'];
        } elseif (is_array($types)) {
            $sync = array_intersect(Arr::get($key, 'sync'), $types);
        }
        $key['sync'] = array_values($sync);
        $this->downloadKeys($key);

        return true;
    }

    private function downloadKeys(array $config): bool
    {
        $path = $this->getPath($config);
        $sync = Arr::get($config, 'sync');
        $revisions = $this->getStorage()->directories($path);
        if (empty($revisions) && $this->isStrict) {
            $factory = app(KeyFactory::class)->load($config)->generate();
            $this->saveFromFactory($factory);
            $this->downloadKeys($config);
        }
        foreach ($revisions as $revision) {
            $keys = $this->getStorage()->files($revision);
            $files = $this->filterKeys($keys, $sync);
            foreach ($files as $file) {
                $this->downloadKey($file);
            }
        }

        return true;
    }

    private function downloadKey(string $path)
    {
        try {
            $file = $this->getStorage()->get($path);
            $localFile = $this->getLocalStorage()->put($path, $file);
        } catch (FileNotFoundException $e) {
            return false;
        }
    }

    public function saveFromFactory(KeyFactory $factory): bool
    {
        if ($factory->isFinished()) {
            $this->lastPrivate = $factory->getPrivateKey();
            $this->lastPublic = $factory->getPublicKey();
            $this->isDirty = true;
            $this->saveKey($factory->getConfig());

            return true;
        }

        return false;
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
            $this->getStorage()->put("{$path}/{$filename}.pub", $this->lastPublic);
        }
        //  TODO: This should be replace with a quick validation of a key.
        if ($this->lastPrivate !== null) {
            $this->getStorage()->put("{$path}/{$filename}.key", $this->lastPrivate);
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
        if (! key_exists($keyName, $this->keys)) {
            throw new \InvalidArgumentException(sprintf('The key "%s" is NOT defined.', $keyName));
        }

        return true;
    }

    /**
     * @return callable
     */
    public static function getRevision(): callable
    {
        return fn () => time();
    }

    /**
     * @param array|null $config
     *
     * @return bool
     */
    public function rotateKeys(?array $config = null): bool
    {
        try {
            if (! $config) {
                $rotateKeys = array_values($this->keys);
            } else {
                $rotateKeys = [$config];
            }
            foreach ($rotateKeys as $rotateKey) {
                $path = $this->getPath($rotateKey);
                $revisions = $this->getStorage()->directories($path);
                $revisions = array_slice(
                    $revisions,
                    0,
                    count($revisions) - (int)Arr::get($config, 'keep', 1)
                );
                foreach ($revisions as $revision) {
                    $result = $this->getStorage()->deleteDirectory($revision);
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

    /**
     * @return Filesystem
     */
    private function getStorage(): Filesystem
    {
        return Storage::disk(config('manager.storage'));
    }


    private function getLocalStorage(): Filesystem
    {
        return Storage::disk(config('manager.local_storage'));
    }

    private function filterKeys(array $keys, array $filters = ['*'])
    {
        return array_filter(
            $keys,
            function ($file) use ($filters) {
                foreach ($filters as $filter) {
                    if (Str::endsWith($file, $filter) || $filter == '*') {
                        return true;
                    }

                    return false;
                }

                return false;
            }
        );
    }

    public function getPathOfPrivateKey()
    {
        $path = $this->getLocalStorage()->directories("{$this->getPath([])}/roots")[0] . '/root.key';

        return $this->getLocalStorage()->exists($path) ? $path : false;
    }

    public function getPathOfPublicKey()
    {
        $path = $this->getLocalStorage()->directories("{$this->getPath([])}/roots")[0] . '/root.pub';

        return $this->getLocalStorage()->exists($path) ? $path : false;
    }

    public function getPrivateKey()
    {
        $path = $this->getLocalStorage()->directories("{$this->getPath([])}/roots")[0] . '/root.key';

        if ($this->fileExists($path)) {
            return $this->getContentFile($path);
        }

        return false;
    }

    public function getPublicKey()
    {
        $path = $this->getLocalStorage()->directories("{$this->getPath([])}/roots")[0] . '/root.pub';

        if ($this->fileExists($path)) {
            return $this->getContentFile($path);
        }

        return false;
    }

    private function getContentFile(string $path)
    {
        return $this->getLocalStorage()->get($path);
    }

    private function fileExists(string $path)
    {
        return $this->getLocalStorage()->exists($path);
    }
}
