<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Support;

/**
 * Utility class for wildcard permission pattern matching.
 *
 * Supports patterns like:
 *   - `users.*` matches `users.view`, `users.create`
 *   - `*.view` matches `users.view`, `posts.view`
 *   - `*` matches any single-segment permission
 *
 * The `*` wildcard matches a single segment (does not cross dots).
 */
final class WildcardMatcher
{
    /** @var array<string, string> Compiled regex pattern cache */
    private static array $patternCache = [];

    /**
     * Check if a pattern contains wildcard characters.
     */
    public static function isWildcard(string $pattern): bool
    {
        return str_contains($pattern, '*');
    }

    /**
     * Check if a permission name matches a wildcard pattern.
     */
    public static function matches(string $pattern, string $permission): bool
    {
        // Exact match shortcut
        if ($pattern === $permission) {
            return true;
        }

        // Not a wildcard pattern
        if (! self::isWildcard($pattern)) {
            return false;
        }

        $regex = self::compilePattern($pattern);

        return preg_match($regex, $permission) === 1;
    }

    /**
     * Expand a wildcard pattern against a list of permission names.
     *
     * @param  array<string>  $allPermissions
     * @return array<string>
     */
    public static function expand(string $pattern, array $allPermissions): array
    {
        // Not a wildcard - return as-is if exists in list
        if (! self::isWildcard($pattern)) {
            return in_array($pattern, $allPermissions, true) ? [$pattern] : [];
        }

        $matched = [];

        foreach ($allPermissions as $permission) {
            if (self::matches($pattern, $permission)) {
                $matched[] = $permission;
            }
        }

        return $matched;
    }

    /**
     * Clear the pattern cache.
     */
    public static function clearCache(): void
    {
        self::$patternCache = [];
    }

    /**
     * Compile a wildcard pattern to a regex.
     *
     * The `*` matches any characters except dots (single segment).
     */
    private static function compilePattern(string $pattern): string
    {
        if (isset(self::$patternCache[$pattern])) {
            return self::$patternCache[$pattern];
        }

        // Escape special regex characters except *
        $regex = preg_quote($pattern, '/');

        // Replace \* with [^.]+ (matches any characters except dots)
        $regex = str_replace('\\*', '[^.]+', $regex);

        // Anchor the pattern
        $regex = '/^'.$regex.'$/';

        self::$patternCache[$pattern] = $regex;

        return $regex;
    }
}
