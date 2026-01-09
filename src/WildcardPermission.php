<?php

declare(strict_types=1);

namespace OffloadProject\Mandate;

use Illuminate\Support\Collection;
use OffloadProject\Mandate\Contracts\WildcardHandler;
use OffloadProject\Mandate\Models\Permission;

/**
 * Default wildcard permission handler.
 *
 * Supports the following patterns:
 * - "article:*" matches "article:view", "article:edit", "article:delete"
 * - "*.view" matches "article:view", "user:view", "post:view"
 * - "*" matches everything
 * - "article:edit,delete" matches "article:edit" and "article:delete"
 *
 * Uses colon (:) as the part delimiter and asterisk (*) as the wildcard.
 */
final class WildcardPermission implements WildcardHandler
{
    private const PART_DELIMITER = ':';

    private const SUBPART_DELIMITER = ',';

    private const WILDCARD = '*';

    private const MAX_CACHE_SIZE = 1000;

    /**
     * @var array<string, string> Cache of compiled regex patterns
     */
    private array $patternCache = [];

    /**
     * {@inheritdoc}
     */
    public function matches(string $pattern, string $permission): bool
    {
        // Exact match
        if ($pattern === $permission) {
            return true;
        }

        // Universal wildcard
        if ($pattern === self::WILDCARD) {
            return true;
        }

        // No wildcard in pattern, must be exact match
        if (! $this->containsWildcard($pattern)) {
            return $pattern === $permission;
        }

        // Handle subpart expansion (e.g., "article:edit,delete")
        if (str_contains($pattern, self::SUBPART_DELIMITER)) {
            return $this->matchesWithSubparts($pattern, $permission);
        }

        // Wildcard pattern matching
        $regex = $this->patternToRegex($pattern);

        return preg_match($regex, $permission) === 1;
    }

    /**
     * {@inheritdoc}
     */
    public function getMatchingPermissions(string $pattern, Collection $permissions): Collection
    {
        return $permissions->filter(
            fn (Permission $permission) => $this->matches($pattern, $permission->name)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function containsWildcard(string $pattern): bool
    {
        return str_contains($pattern, self::WILDCARD);
    }

    /**
     * Clear the pattern cache.
     */
    public function clearCache(): void
    {
        $this->patternCache = [];
    }

    /**
     * Parse a permission string into its component parts.
     *
     * @return array{resource: string, action: string|null}
     */
    public function parse(string $permission): array
    {
        $parts = explode(self::PART_DELIMITER, $permission, 2);

        return [
            'resource' => $parts[0],
            'action' => $parts[1] ?? null,
        ];
    }

    /**
     * Build a permission string from resource and action.
     */
    public function build(string $resource, ?string $action = null): string
    {
        if ($action === null) {
            return $resource;
        }

        return $resource.self::PART_DELIMITER.$action;
    }

    /**
     * Handle patterns with subpart delimiters (e.g., "article:edit,delete").
     */
    private function matchesWithSubparts(string $pattern, string $permission): bool
    {
        $parts = explode(self::PART_DELIMITER, $pattern);
        $permissionParts = explode(self::PART_DELIMITER, $permission);

        // Part count must match
        if (count($parts) !== count($permissionParts)) {
            return false;
        }

        foreach ($parts as $index => $part) {
            $permissionPart = $permissionParts[$index];

            // Check if this part has subparts
            if (str_contains($part, self::SUBPART_DELIMITER)) {
                $subparts = explode(self::SUBPART_DELIMITER, $part);

                // Permission part must match one of the subparts
                if (! in_array($permissionPart, $subparts, true) && ! in_array(self::WILDCARD, $subparts, true)) {
                    return false;
                }
            } elseif ($part !== self::WILDCARD && $part !== $permissionPart) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convert a wildcard pattern to a regex.
     */
    private function patternToRegex(string $pattern): string
    {
        if (isset($this->patternCache[$pattern])) {
            return $this->patternCache[$pattern];
        }

        // Bound cache size to prevent memory issues
        if (count($this->patternCache) >= self::MAX_CACHE_SIZE) {
            // Remove oldest half of entries
            $this->patternCache = array_slice($this->patternCache, (int) (self::MAX_CACHE_SIZE / 2), null, true);
        }

        // Escape special regex characters except our wildcards
        $escaped = preg_quote($pattern, '/');

        // Replace escaped wildcards with regex equivalents
        $regex = str_replace(
            preg_quote(self::WILDCARD, '/'),
            '[^:]+', // Match anything except the delimiter
            $escaped
        );

        // Handle universal wildcard at the end (e.g., "article:*" should match "article:view:all")
        if (str_ends_with($pattern, self::WILDCARD)) {
            // Replace the last [^:]+ with .+ to match multiple segments
            $regex = preg_replace('/\[\^:\]\+$/', '.+', $regex);
        }

        $regex = '/^'.$regex.'$/';

        $this->patternCache[$pattern] = $regex;

        return $regex;
    }
}
