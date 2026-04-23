<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Models\Banner;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->unsignedInteger('image_width')->nullable()->after('image');
            $table->unsignedInteger('image_height')->nullable()->after('image_width');
            $table->unsignedInteger('mobile_image_width')->nullable()->after('mobile_image');
            $table->unsignedInteger('mobile_image_height')->nullable()->after('mobile_image_width');
        });

        // Backfill dimensions for existing banners
        foreach (Banner::all() as $banner) {
            $this->populate($banner, 'image', 'image_width', 'image_height');
            $this->populate($banner, 'mobile_image', 'mobile_image_width', 'mobile_image_height');
            $banner->saveQuietly();
        }
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn([
                'image_width', 'image_height',
                'mobile_image_width', 'mobile_image_height',
            ]);
        });
    }

    private function populate(Banner $banner, string $col, string $wCol, string $hCol): void
    {
        if (empty($banner->$col)) return;
        $path = Storage::disk('public')->path($banner->$col);
        if (! is_file($path)) return;
        $info = @getimagesize($path);
        if ($info) {
            $banner->$wCol = $info[0];
            $banner->$hCol = $info[1];
        }
    }
};
