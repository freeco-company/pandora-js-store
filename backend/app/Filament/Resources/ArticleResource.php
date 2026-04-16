<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use BackedEnum;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-text';
    protected static string | UnitEnum | null $navigationGroup = '內容管理';
    protected static ?string $navigationLabel = '文章';
    protected static ?string $modelLabel = '文章';
    protected static ?string $pluralModelLabel = '文章';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Group::make()->schema([
                    Section::make('文章內容')->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->label('標題'),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->label('URL Slug'),
                        Forms\Components\RichEditor::make('content')
                            ->label('內容')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('excerpt')
                            ->label('摘要')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
                ])->columnSpan(2),

                Group::make()->schema([
                    Section::make('發布設定')->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'published' => '已發布',
                                'draft' => '草稿',
                            ])
                            ->default('published')
                            ->label('狀態'),
                        Forms\Components\Toggle::make('is_pinned')
                            ->label('置頂'),
                        Forms\Components\Select::make('source_type')
                            ->options([
                                'blog' => '部落格',
                                'news' => '最新消息',
                                'brand' => '品牌故事',
                                'recommend' => '推薦文',
                            ])
                            ->required()
                            ->label('分類'),
                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('發布時間'),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->label('排序'),
                    ]),

                    Section::make('封面')->schema([
                        Forms\Components\FileUpload::make('featured_image')
                            ->image()
                            ->directory('articles')
                            ->disk('public')
                            ->getUploadedFileNameForStorageUsing(fn ($file) => 'articles/' . $file->getClientOriginalName())
                            ->label('封面圖'),
                    ]),
                ])->columnSpan(1),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image')
                    ->circular()
                    ->getStateUsing(fn ($record) => $record->featured_image ? preg_replace('#^/storage/#', '', $record->featured_image) : null)
                    ->disk('public')
                    ->label('圖'),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->label('標題'),
                Tables\Columns\BadgeColumn::make('source_type')
                    ->colors([
                        'primary' => 'blog',
                        'success' => 'news',
                        'warning' => 'brand',
                        'info' => 'recommend',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'blog' => '部落格',
                        'news' => '最新消息',
                        'brand' => '品牌故事',
                        'recommend' => '推薦文',
                        default => $state,
                    })
                    ->label('分類'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'published',
                        'warning' => 'draft',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'published' => '已發布',
                        'draft' => '草稿',
                        default => $state,
                    })
                    ->label('狀態'),
                Tables\Columns\IconColumn::make('is_pinned')
                    ->boolean()
                    ->label('置頂'),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->label('發布日'),
            ])
            ->defaultSort('is_pinned', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('source_type')
                    ->options([
                        'blog' => '部落格',
                        'news' => '最新消息',
                        'brand' => '品牌故事',
                        'recommend' => '推薦文',
                    ])
                    ->label('分類'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'published' => '已發布',
                        'draft' => '草稿',
                    ])
                    ->label('狀態'),
                Tables\Filters\TernaryFilter::make('is_pinned')
                    ->label('置頂'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
