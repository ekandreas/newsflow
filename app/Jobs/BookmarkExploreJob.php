<?php

namespace App\Jobs;

use App\Crawlers\DomainCrawler;
use App\Models\Bookmark;
use App\Models\Domain;
use App\Models\Provider;
use Embed\Embed;
use Essence\Essence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use League\Uri\Uri;
use shweshi\OpenGraph\OpenGraph;

class BookmarkExploreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Bookmark $bookmark;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Bookmark $bookmark)
    {
        $this->bookmark = $bookmark;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $embed = new Embed();

        $info = $embed->get($this->bookmark->url);

        $this->bookmark->url = $info->url ?? $this->bookmark->url;
        $this->bookmark->name = $info->title ?? 'No Name';
        $this->bookmark->description = $info->description ?? null;
        $this->bookmark->image = $info->image ?? null;
        $this->bookmark->type = 'text';

        if($info->keywords) {
            $this->bookmark->syncTags($info->keywords);
        }

        $essence = new Essence();
        $media = $essence->extract($this->bookmark->url);
        if($media) {
            $this->bookmark->type = $media->type;
        }

        if($info->providerName && $info->providerUrl) {
            $provider = Provider::where('url', $info->providerUrl)->first();
            if(!$provider) {
                $provider = Provider::create([
                    'name' => $info->providerName,
                    'url' => $info->providerUrl,
                ]);
            }
            else {
                $provider->name = $info->providerName;
                $provider->save();
            }
            $provider->syncTags($this->bookmark->tags);

            $this->bookmark->provider_id = $provider->id;
        }

        $this->bookmark->saveQuietly();

        return;

        // $uri = Uri::createFromString($this->bookmark->url);
        // $host = $uri->getHost();

        // $data = $og->fetch($host, true);

        // ray($data);

        // $domain = Domain::where('url', $host)->first();
        // if(!$domain) {
        //     $domain = Domain::create([
        //         'url' => $host,
        //         'name' => $data['title'] ?? 'No Name',
        //     ]);
        // }

        // $domain->update([
        //     'name' => $data['title'] ?? 'No Name',
        //     'description' => $data['description'] ?? null,
        // ]);

        // $domain->syncTags($this->bookmark->tags);

        // Crawler::create()
        //     ->setCrawlObserver(new DomainCrawler)
        //     ->startCrawling($this->bookmark->url);
    }
}
