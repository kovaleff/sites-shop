<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * The unique categories from the project description.
     *
     * @var array<int, string>
     */
    public const CATEGORIES = [
        'Авто-Мото',
        'Бизнес и финансы',
        'Бытовая и эл.техника',
        'Бытовая техника',
        'Женский раздел',
        'Здоровье и медицина',
        'Интернет, маркетинг и SEO',
        'Кино и музыка',
        'Компьютеры и IT',
        'Культура и искусство',
        'Мебель, интерьер и быт',
        'Мобильные технологии',
        'Мода и красота',
        'Недвижимость',
        'Образование и наука',
        'Отдых, развлечения и хобби',
        'Офис',
        'Политика и законы',
        'Природа и экология',
        'Производство, торговля',
        'Работа и вакансии',
        'Реклама и СМИ',
        'Рецепты и еда',
        'Сельское хозяйство и производство',
        'Спорт и отдых',
        'Строительство и ремонт',
        'Товары и услуги',
        'Туризм и путешествия',
        'Фотография и фотоуслуги',
        'Хобби, увлечения',
        'Юмор и развлечения',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::CATEGORIES as $name) {
            Category::query()->firstOrCreate(['name' => $name]);
        }
    }
}
