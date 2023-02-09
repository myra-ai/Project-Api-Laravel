<?php

namespace Database\Seeders;

use App\Http\Controllers\API;
use App\Models\LiveStreamCompanies as mLiveStreamCompanies;
use App\Models\LiveStreamCompanyUsers as mLiveStreamCompanyUsers;
use App\Models\LiveStreamCompanyTokens as mLiveStreamCompanyTokens;
use App\Models\LiveStreamProductGroups as mLiveStreamProductGroups;
use App\Models\LiveStreamProducts as mLiveStreamProducts;
use App\Models\LiveStreamProductsImages as mLiveStreamProductsImages;
use App\Models\LiveStreams as mLiveStreams;
use App\Models\Stories as mStories;
use App\Models\Tenants as mTenants;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        if (Storage::directoryMissing('public/images')) {
            Storage::makeDirectory('public/images');
        }

        if (Storage::directoryMissing('public/images/thumbnails')) {
            Storage::makeDirectory('public/images/thumbnails');
        }

        if (Storage::directoryMissing('public/videos')) {
            Storage::makeDirectory('public/videos');
        }

        if (Storage::directoryMissing('public/unknown')) {
            Storage::makeDirectory('public/unknown');
        }

        $tenant_id = '2278df21-2f4f-40dd-918a-6650eb1e3e91'; // Str::uuid()->toString();
        $company_id = '1ad782fc-cc4b-4ba7-b91c-ec90d3464529'; // Str::uuid()->toString();
        $user_id = '19a329fa-0d45-43cf-b878-b428d0b33ad2'; // Str::uuid()->toString();

        mTenants::create([
            'id' => $tenant_id,
        ]);

        $avatar = API::registerMediaFromUrl('https://cdn.eibly.com/images/avatars/347-3474759_icon-square-big-example-handle-with-care-symbol.png', alt: 'Avatar', desc: 'Default avatar');
        $logo = API::registerMediaFromUrl('https://cdn.eibly.com/images/avatars/4545133762933_59a0cfb8e43a20afa86b_88.png', alt: 'Logo', desc: 'Default logo');

        mLiveStreamCompanies::create([
            'id' => $company_id,
            'tenant_id' => $tenant_id,
            'name' => 'Bliver',
            'primary_color' => '000000',
            'cta_color' => '000000',
            'accent_colors' => '000000',
            'text_chat_color' => '000000',
            'rtmp_key' => null,
            'avatar' => $avatar->id,
            'logo' => $logo->id,
            'stories_is_embedded' => true,
            'livestream_autoopen' => false,
        ]);

        mLiveStreamCompanyUsers::create([
            'id' => $user_id,
            'company_id' => $company_id,
            'role' => 1,
            'name' => 'Kleber Santos',
            'email' => 'kleber.santos@gobliver.com',
            'password' => Hash::make('rbSwh7DQ72de98A7uX75CPTz'),
            'email_verified_at' => now()->format('Y-m-d H:i:s.u'),
            'phone_country_code' => 'BR',
            'phone' => '15981118982',
            'address' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'zip' => '10001',
            'country' => 'USA',
            'is_master' => true,
        ]);

        mLiveStreamCompanyTokens::create([
            'token' => Str::random(60),
            'user_id' => $user_id,
            'expires_at' => now()->addDays(30)->format('Y-m-d H:i:s.u'),
        ]);

        API::registerMediaFromUrl('https://cdn.eibly.com/images/placeholders/32.webp', alt: 'Placeholder 32', desc: 'Default image placeholder');
        API::registerMediaFromUrl('https://cdn.eibly.com/images/placeholders/64.webp', alt: 'Placeholder 64', desc: 'Default image placeholder');
        API::registerMediaFromUrl('https://cdn.eibly.com/images/placeholders/128.webp', alt: 'Placeholder 128', desc: 'Default image placeholder');
        API::registerMediaFromUrl('https://cdn.eibly.com/images/placeholders/300.webp', alt: 'Placeholder 300', desc: 'Default image placeholder');
        API::registerMediaFromUrl('https://cdn.eibly.com/images/placeholders/512.webp', alt: 'Placeholder 512', desc: 'Default image placeholder');
        API::registerMediaFromUrl('https://cdn.eibly.com/images/placeholders/1024.webp', alt: 'Placeholder 1024', desc: 'Default image placeholder');
        API::registerMediaFromUrl('https://cdn.eibly.com/images/placeholders/1200.webp', alt: 'Placeholder 1200', desc: 'Default image placeholder');
        API::registerMediaFromUrl('https://cdn.eibly.com/images/placeholders/1400.webp', alt: 'Placeholder 1400', desc: 'Default image placeholder');
        API::registerMediaFromUrl('https://cdn.eibly.com/images/placeholders/1600.webp', alt: 'Placeholder 1600', desc: 'Default image placeholder');
        API::registerMediaFromUrl('https://cdn.eibly.com/images/placeholders/1920x1080.webp', alt: 'Placeholder 1920x1080', desc: 'Landscape image placeholder');
        API::registerMediaFromUrl('https://cdn.eibly.com/images/placeholders/1080x1920.webp', alt: 'Placeholder 1080x1920', desc: 'Portrait image placeholder');

        $story_id = '3820b3ac-b55a-4e52-a4fa-97fbbb532c39'; // Str::uuid()->toString();
        $story_id2 = '4b5912d4-2671-4cd2-a736-4f8da90b2ec7'; // Str::uuid()->toString();
        $story_id3 = '51841316-8b7c-402d-961d-cd00c7018338'; // Str::uuid()->toString();

        $media_story = API::registerMediaFromUrl('https://cdn.eibly.com/video/01d247ad-f847-4b95-84a0-54cf271de966-mixkit-man-under-multicolored-lights-1237-medium.mp4');
    
        mStories::create([
            'id' => $story_id,
            'company_id' => $company_id,
            'title' => 'My First Story #1',
            'publish' => true,
            'status' => 'ACTIVE',
            'media_id' => $media_story->id,
        ]);

        $media_story = API::registerMediaFromUrl('https://cdn.eibly.com/video/high.mp4');

        mStories::create([
            'id' => $story_id2,
            'company_id' => $company_id,
            'title' => 'My Second Story #2',
            'publish' => true,
            'status' => 'ACTIVE',
            'media_id' => $media_story->id,
        ]);

        $media_story = API::registerMediaFromUrl('https://cdn.eibly.com/video/high2.mp4');

        mStories::create([
            'id' => $story_id3,
            'company_id' => $company_id,
            'title' => 'My Third Story #3',
            'publish' => true,
            'status' => 'ACTIVE',
            'media_id' => $media_story->id,
        ]);

        $stream_id = '1db4344c-43ed-41a8-a575-d54fe81a7ffa'; // Str::uuid()->toString();

        mLiveStreams::create([
            'id' => $stream_id,
            'company_id' => $company_id,
            'title' => 'My First Stream',
            'live_id' => '550c707f-8a3f-4e4b-8cf3-b815b00fb6ea',
            'stream_key' => '550c707f-8a3f-4e4b-8cf3-b815b00fb6ea',
            'latency_mode' => 'low',
            'audio_only' => false,
            'orientation' => 'landscape',
            'note' => 'This is my first stream',
            'status' => 'created',
        ]);

        $link = API::registerLink('https://boxicons.com/');
        $link2 = API::registerLink('https://youtube.com/');
        $link3 = API::registerLink('https://google.com/');

        $product_image = [
            API::registerMediaFromUrl('https://cdn.eibly.com/images/photo-1670450734728-c4d6f59134f0.jpg'),
            API::registerMediaFromUrl('https://cdn.eibly.com/images/photo-1670612389555-1de63603416a.jpg'),
            API::registerMediaFromUrl('https://cdn.eibly.com/images/photo-1670759699765-96f760531c18.jpg'),
            API::registerMediaFromUrl('https://cdn.eibly.com/images/photo-1671035812235-fc43ebce5ac8.jpg'),
            API::registerMediaFromUrl('https://cdn.eibly.com/images/photo-1671230926745-661ecd5b0d40.jpg'),
            API::registerMediaFromUrl('https://cdn.eibly.com/images/photo-1671419742115-7cd22c6eae73.jpg'),
            API::registerMediaFromUrl('https://cdn.eibly.com/images/photo-1671465184864-1fe3ea6c7734.jpg'),
            API::registerMediaFromUrl('https://cdn.eibly.com/images/photo-1671470394194-ab66585bd009.jpg'),
        ];

        $product_id = [
            Str::uuid()->toString(),
            Str::uuid()->toString(),
            Str::uuid()->toString(),
        ];

        mLiveStreamProducts::create([
            'id' => $product_id[0],
            'company_id' => $company_id,
            'title' => __('My First Product'),
            'description' => 'Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.',
            'price' => rand(100, 3000),
            'link_id' => $link->id,
            'currency' => 'BRL',
            'status' => 1,
        ]);

        mLiveStreamProducts::create([
            'id' => $product_id[1],
            'company_id' => $company_id,
            'title' => __('My Second Product'),
            'description' => 'It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using \'Content here, content here\', making it look like readable English',
            'price' => rand(50, 250) . rand(1, 99),
            'link_id' => $link2->id,
            'currency' => 'BRL',
            'status' => 1,
        ]);

        mLiveStreamProducts::create([
            'id' => $product_id[2],
            'company_id' => $company_id,
            'title' => __('My Third Product'),
            'description' => 'Contrary to popular belief, Lorem Ipsum is not simply random text. It has roots in a piece of classical Latin literature from 45 BC, making it over 2000 years old.',
            'price' => rand(10, 80),
            'link_id' => $link3->id,
            'currency' => 'USD',
            'status' => 1,
        ]);

        mLiveStreamProductsImages::create([
            'id' => Str::uuid()->toString(),
            'product_id' => $product_id[0],
            'media_id' => $product_image[0]->id,
        ]);

        mLiveStreamProductsImages::create([
            'id' => Str::uuid()->toString(),
            'product_id' => $product_id[0],
            'media_id' => $product_image[1]->id,
        ]);

        mLiveStreamProductsImages::create([
            'id' => Str::uuid()->toString(),
            'product_id' => $product_id[1],
            'media_id' => $product_image[2]->id,
        ]);

        mLiveStreamProductsImages::create([
            'id' => Str::uuid()->toString(),
            'product_id' => $product_id[1],
            'media_id' => $product_image[5]->id,
        ]);

        mLiveStreamProductsImages::create([
            'id' => Str::uuid()->toString(),
            'product_id' => $product_id[2],
            'media_id' => $product_image[4]->id,
        ]);

        mLiveStreamProductsImages::create([
            'id' => Str::uuid()->toString(),
            'product_id' => $product_id[2],
            'media_id' => $product_image[6]->id,
        ]);

        mLiveStreamProductGroups::create([
            'id' => Str::uuid()->toString(),
            'product_id' => $product_id[0],
            'stream_id' => $stream_id,
        ]);
        
        mLiveStreamProductGroups::create([
            'id' => Str::uuid()->toString(),
            'product_id' => $product_id[1],
            'stream_id' => $stream_id,
        ]);

        mLiveStreamProductGroups::create([
            'id' => Str::uuid()->toString(),
            'product_id' => $product_id[2],
            'stream_id' => $stream_id,
        ]);

        mLiveStreamProductGroups::create([
            'id' => Str::uuid()->toString(),
            'product_id' => $product_id[0],
            'story_id' => $story_id,
        ]);
        
        mLiveStreamProductGroups::create([
            'id' => Str::uuid()->toString(),
            'product_id' => $product_id[1],
            'story_id' => $story_id2,
        ]);

        mLiveStreamProductGroups::create([
            'id' => Str::uuid()->toString(),
            'product_id' => $product_id[2],
            'story_id' => $story_id2,
        ]);
    }
}
