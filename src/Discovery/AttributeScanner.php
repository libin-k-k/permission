<?php

namespace Libinkk\Permission\Discovery;

use Libinkk\Permission\Attributes\Permission as PermissionAttribute;
use ReflectionAttribute;
use ReflectionClass;
use Throwable;

class AttributeScanner
{
    /**
     * @param  list<string>  $paths
     * @return list<array<string, mixed>>
     */
    public function scan(array $paths): array
    {
        $discovered = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            foreach ($this->phpFiles($path) as $file) {
                foreach ($this->scanFile($file) as $permission) {
                    $key = ($permission['guard'] ?? 'web').'|'.$permission['name'];
                    $discovered[$key] = $permission;
                }
            }
        }

        return array_values($discovered);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function scanFile(string $file): array
    {
        $code = @file_get_contents($file);

        if ($code === false || ! str_contains($code, 'Permission')) {
            return [];
        }

        $fqcn = $this->classFromFile($file, $code);

        if ($fqcn === null) {
            return [];
        }

        try {
            if (! class_exists($fqcn, false) && ! interface_exists($fqcn, false) && ! trait_exists($fqcn, false)) {
                require_once $file;
            }

            if (! class_exists($fqcn)) {
                return [];
            }

            $reflection = new ReflectionClass($fqcn);
        } catch (Throwable) {
            return [];
        }

        $found = [];

        foreach ($this->attributesOn($reflection) as $attribute) {
            $found[] = $this->toArray($attribute, $fqcn, null, $file);
        }

        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            foreach ($this->attributesOn($method) as $attribute) {
                $found[] = $this->toArray($attribute, $fqcn, $method->getName(), $file);
            }
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    protected function phpFiles(string $path): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * @return list<PermissionAttribute>
     */
    protected function attributesOn(object $reflector): array
    {
        if (! method_exists($reflector, 'getAttributes')) {
            return [];
        }

        return array_map(
            static fn (ReflectionAttribute $attribute) => $attribute->newInstance(),
            $reflector->getAttributes(PermissionAttribute::class)
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArray(
        PermissionAttribute $attribute,
        ?string $class,
        ?string $method,
        string $file,
    ): array {
        $parts = str_contains($attribute->name, '.')
            ? explode('.', $attribute->name, 2)
            : [null, null];

        return [
            'name' => $attribute->name,
            'description' => $attribute->description,
            'group' => $attribute->group,
            'resource' => $attribute->resource ?? $parts[0],
            'action' => $attribute->action ?? $parts[1],
            'guard' => $attribute->guard ?? (string) config('permission.default_guard', 'web'),
            'risk_level' => $attribute->risk_level,
            'is_dangerous' => $attribute->is_dangerous,
            'requires_audit' => $attribute->requires_audit,
            'source_class' => $class,
            'source_method' => $method,
            'source_file' => $file,
        ];
    }

    protected function classFromFile(string $file, string $code): ?string
    {
        $namespace = null;

        if (preg_match('/namespace\s+([^;]+);/', $code, $matches)) {
            $namespace = trim($matches[1]);
        }

        if (! preg_match('/\b(class|interface|trait|enum)\s+(\w+)/', $code, $matches)) {
            return null;
        }

        $class = $matches[2];

        return $namespace ? $namespace.'\\'.$class : $class;
    }
}
