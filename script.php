<?php

namespace App\Helpers;

use App\Models\City;
use App\Models\Region;
use Exception;
use Illuminate\Support\Str;

class GeoHelper
{
    public bool $found = false;

    /** @var string|null Почтовый индекс. Пример: "299700" */
    public ?string $postal_code = null;
    /** @var string|null Федеральный округ. Пример: "Северо-Кавказский" */
    public ?string $federal_district = null;
    /** @var string|null Регион с типом. Пример: "Респ Адыгея" */
    public ?string $region_with_type = null;
    /** @var string|null Город с типом. Пример: "г Севастополь" */
    public ?string $city_with_type = null;

    /** @var Region|null Регион */
    public ?Region $region = null;

    /** @var City|null Город */
    public ?City $city = null;

    public string $search;
    private array $cities = [];
    private array $regions = [];


    /**
     * @throws Exception
     */
    public function __construct(string $text_with_address = null)
    {
        if ($text_with_address === null) {
            return;
        }

        $this->parse($text_with_address);
    }

    public function isFound(): bool
    {
        return $this->found;
    }

    /**
     * Пропарсить текст на наличие региона или города
     *
     * @throws Exception
     */
    public function parse(string $search): bool
    {
        $this->setSearch($search);
        $this->reset();
        $this->loadData();
        $this->tryFindGeo();

        return $this->found;
    }

    /**
     * @param string $search
     * @throws Exception
     */
    private function setSearch(string $search): void
    {
        if (empty($search)) {
            throw new Exception('Передана пустая строка');
        }

        $this->search = $search;
    }

    private function loadCities(): void
    {
        if (!empty($this->cities)) {
            return;
        }

        $cities = City::orderBy('name')->get();

        foreach ($cities as $city) {
            $this->cities[$city->name] = $city->id;

            if(empty($city->synonyms)) {
                continue;
            }
            foreach ($city->synonyms as $synonym) {
                $this->cities[$synonym] = $city->id;
            }
        }
    }

    private function loadRegions(): void
    {
        if (!empty($this->regions)) {
            return;
        }

        $regions = Region::orderBy('name')->get();

        foreach ($regions as $region) {
            $this->regions[$region->name] = $region->id;
            if(empty($region->synonyms)) {
                continue;
            }
            foreach ($region->synonyms as $synonym) {
                $this->regions[$synonym] = $region->id;
            }
        }
    }

    private function tryFindGeo(): void
    {
        $text = Str::lower($this->getSearch());

        foreach ($this->cities as $city => $city_id) {
            if (Str::contains($text, Str::lower($city))) {
                $this->city = City::with('region')->find($city_id);
                if (empty($this->region)) {
                    $this->region = $this->city->region;
                }

                $this->found = true;
                break;
            }
        }
        if ($this->found) {
            $this->setProperty();
            return;
        }

        foreach ($this->regions as $region => $region_id) {
            if (Str::contains($text, Str::lower($region))) {
                $this->found = true;
                $this->region = Region::find($region_id);
                break;
            }
        }

        $this->setProperty();

    }

    /**
     * @return string
     */
    private function getSearch(): string
    {
        $search = preg_replace("/[^\d\w\- ]+/ui", ' ', $this->search); // Удаляем знаки препинания
        $search = preg_replace('/\s+/i', ' ', $search); // Удаляем двойные пробелы
        $search = str_replace([' ', '&nbsp;', '&amp;nbsp;'], ' ', $search);
        $search = str_replace([' ', '&nbsp;', '&amp;nbsp;'], ' ', $search);
        $search = strip_tags($search);

        return trim($search);
    }

    private function reset(): void
    {
        $this->found = false;
        $this->city = null;
        $this->region = null;
        $this->postal_code = null;
        $this->federal_district = null;
        $this->region_with_type = null;
        $this->city_with_type = null;
    }

    private function loadData(): void
    {
        $this->loadCities();
        $this->loadRegions();
    }

    private function setProperty(): void
    {
        if ($this->found === false) {
            return;
        }

        if (!empty($this->region) && !empty($this->city)) {
            $this->postal_code = $this->city->postal_code;
            $this->federal_district = $this->region->federal_district;
            $this->region_with_type = $this->region->region_with_type;
            $this->city_with_type = $this->city->city_with_type;
        } elseif (!empty($this->region)) {
            $this->postal_code = $this->region->postal_code;
            $this->federal_district = $this->region->federal_district;
            $this->region_with_type = $this->region->region_with_type;
            $this->city_with_type = null;
        }

    }
}
