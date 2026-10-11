<?php

namespace App\Support;

use Illuminate\Support\Collection;

final readonly class Story
{
    /**
     * @param  list<string>  $pages
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $description,
        public string $emoji,
        public string $color,
        public array $pages,
    ) {}

    /**
     * Get every configured story.
     *
     * @return Collection<int, Story>
     */
    public static function all(): Collection
    {
        return collect(config('stories', []))
            ->map(fn (array $story, string $slug): Story => self::fromConfig($slug, $story))
            ->values();
    }

    /**
     * Find a configured story by its slug.
     */
    public static function find(string $slug): ?Story
    {
        $story = config("stories.{$slug}");

        return is_array($story) ? self::fromConfig($slug, $story) : null;
    }

    /**
     * Find a configured story by its slug or abort with a 404.
     */
    public static function findOrFail(string $slug): Story
    {
        return self::find($slug) ?? abort(404);
    }

    /**
     * Get the number of pages in the story.
     */
    public function pageCount(): int
    {
        return count($this->pages);
    }

    /**
     * Get the text of the given (1-indexed) page.
     */
    public function pageText(int $pageNumber): ?string
    {
        return $this->pages[$pageNumber - 1] ?? null;
    }

    /**
     * Build a story from its configuration array.
     *
     * @param  array{title: string, description?: string, emoji?: string, color?: string, pages: list<string>}  $story
     */
    private static function fromConfig(string $slug, array $story): Story
    {
        return new Story(
            slug: $slug,
            title: $story['title'],
            description: $story['description'] ?? '',
            emoji: $story['emoji'] ?? '📖',
            color: $story['color'] ?? 'sky',
            pages: array_values($story['pages']),
        );
    }
}
