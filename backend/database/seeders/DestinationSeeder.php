<?php

namespace Database\Seeders;

use App\Models\Destination;
use Illuminate\Database\Seeder;

class DestinationSeeder extends Seeder
{
    public function run(): void
    {
        $destinations = [
            [
                'name' => 'Paris', 'country' => 'France', 'region' => 'Europe',
                'overview' => 'The French capital, known for world-class museums, cafe culture and landmark architecture.',
                'cuisine' => 'Bakeries, bistros and cheese, with croissants, steak frites and macarons.',
                'budget_level' => 'luxury', 'cost_index' => 80, 'climate' => 'temperate',
                'best_months' => [4, 5, 6, 9, 10],
                'themes' => ['museum', 'art', 'architecture', 'food'],
                'safety_rating' => 4, 'latitude' => 48.8566, 'longitude' => 2.3522,
                'attractions' => [
                    ['name' => 'Louvre Museum', 'tags' => ['museum', 'art', 'history'], 'popularity' => 0.95, 'latitude' => 48.8606, 'longitude' => 2.3376],
                    ['name' => 'Eiffel Tower', 'tags' => ['architecture'], 'popularity' => 0.95, 'latitude' => 48.8584, 'longitude' => 2.2945],
                    ['name' => 'Notre-Dame Cathedral', 'tags' => ['architecture', 'history'], 'popularity' => 0.85, 'latitude' => 48.8530, 'longitude' => 2.3499],
                    ['name' => 'Montmartre and Sacre-Coeur', 'tags' => ['art', 'architecture'], 'popularity' => 0.75, 'latitude' => 48.8867, 'longitude' => 2.3431],
                ],
            ],
            [
                'name' => 'Rome', 'country' => 'Italy', 'region' => 'Europe',
                'overview' => 'The Italian capital, where ancient ruins, Renaissance art and lively piazzas share the same streets.',
                'cuisine' => 'Pasta, pizza al taglio and gelato, with carbonara and cacio e pepe.',
                'budget_level' => 'moderate', 'cost_index' => 65, 'climate' => 'mediterranean',
                'best_months' => [4, 5, 6, 9, 10],
                'themes' => ['history', 'art', 'architecture', 'food'],
                'safety_rating' => 4, 'latitude' => 41.9028, 'longitude' => 12.4964,
                'attractions' => [
                    ['name' => 'Colosseum', 'tags' => ['history', 'architecture'], 'popularity' => 0.95, 'latitude' => 41.8902, 'longitude' => 12.4922],
                    ['name' => 'Vatican Museums', 'tags' => ['museum', 'art', 'history'], 'popularity' => 0.90, 'latitude' => 41.9065, 'longitude' => 12.4536],
                    ['name' => 'Trevi Fountain', 'tags' => ['architecture', 'art'], 'popularity' => 0.85, 'latitude' => 41.9009, 'longitude' => 12.4833],
                    ['name' => 'Pantheon', 'tags' => ['history', 'architecture'], 'popularity' => 0.80, 'latitude' => 41.8986, 'longitude' => 12.4769],
                ],
            ],
            [
                'name' => 'Tokyo', 'country' => 'Japan', 'region' => 'Asia',
                'overview' => 'A vast city mixing neon-lit districts, quiet shrines and some of the best food in the world.',
                'cuisine' => 'Sushi, ramen, izakaya dishes and street snacks.',
                'budget_level' => 'moderate', 'cost_index' => 70, 'climate' => 'temperate',
                'best_months' => [3, 4, 5, 10, 11],
                'themes' => ['food', 'shopping', 'history', 'nightlife'],
                'safety_rating' => 5, 'latitude' => 35.6762, 'longitude' => 139.6503,
                'attractions' => [
                    ['name' => 'Senso-ji Temple', 'tags' => ['history', 'architecture'], 'popularity' => 0.90, 'latitude' => 35.7148, 'longitude' => 139.7967],
                    ['name' => 'Meiji Shrine', 'tags' => ['history', 'nature'], 'popularity' => 0.80, 'latitude' => 35.6764, 'longitude' => 139.6993],
                    ['name' => 'Tsukiji Outer Market', 'tags' => ['food', 'shopping'], 'popularity' => 0.80, 'latitude' => 35.6655, 'longitude' => 139.7707],
                    ['name' => 'Shibuya Crossing', 'tags' => ['nightlife', 'shopping'], 'popularity' => 0.85, 'latitude' => 35.6595, 'longitude' => 139.7005],
                ],
            ],
            [
                'name' => 'Istanbul', 'country' => 'Turkey', 'region' => 'Europe',
                'overview' => 'A city on two continents, with Ottoman palaces, grand mosques and busy bazaars along the Bosphorus.',
                'cuisine' => 'Kebabs, meze, baklava and Turkish tea.',
                'budget_level' => 'budget', 'cost_index' => 45, 'climate' => 'mediterranean',
                'best_months' => [4, 5, 9, 10],
                'themes' => ['history', 'architecture', 'food', 'shopping'],
                'safety_rating' => 3, 'latitude' => 41.0082, 'longitude' => 28.9784,
                'attractions' => [
                    ['name' => 'Hagia Sophia', 'tags' => ['history', 'architecture'], 'popularity' => 0.95, 'latitude' => 41.0086, 'longitude' => 28.9802],
                    ['name' => 'Blue Mosque', 'tags' => ['architecture', 'history'], 'popularity' => 0.85, 'latitude' => 41.0054, 'longitude' => 28.9768],
                    ['name' => 'Grand Bazaar', 'tags' => ['shopping', 'history'], 'popularity' => 0.85, 'latitude' => 41.0107, 'longitude' => 28.9680],
                    ['name' => 'Topkapi Palace', 'tags' => ['museum', 'history'], 'popularity' => 0.80, 'latitude' => 41.0115, 'longitude' => 28.9834],
                ],
            ],
            [
                'name' => 'Bangkok', 'country' => 'Thailand', 'region' => 'Asia',
                'overview' => 'A busy tropical capital of golden temples, canals, street food and nightlife.',
                'cuisine' => 'Pad thai, tom yum and mango sticky rice from street stalls and markets.',
                'budget_level' => 'budget', 'cost_index' => 35, 'climate' => 'tropical',
                'best_months' => [11, 12, 1, 2],
                'themes' => ['food', 'nightlife', 'history', 'shopping'],
                'safety_rating' => 3, 'latitude' => 13.7563, 'longitude' => 100.5018,
                'attractions' => [
                    ['name' => 'Grand Palace', 'tags' => ['history', 'architecture'], 'popularity' => 0.90, 'latitude' => 13.7500, 'longitude' => 100.4913],
                    ['name' => 'Wat Arun', 'tags' => ['architecture', 'history'], 'popularity' => 0.80, 'latitude' => 13.7437, 'longitude' => 100.4889],
                    ['name' => 'Chatuchak Weekend Market', 'tags' => ['shopping', 'food'], 'popularity' => 0.80, 'latitude' => 13.7999, 'longitude' => 100.5502],
                    ['name' => 'Khao San Road', 'tags' => ['nightlife', 'food'], 'popularity' => 0.70, 'latitude' => 13.7589, 'longitude' => 100.4974],
                ],
            ],
            [
                'name' => 'Bali', 'country' => 'Indonesia', 'region' => 'Asia',
                'overview' => 'An Indonesian island of beaches, rice terraces, temples and surf, with a relaxed pace.',
                'cuisine' => 'Nasi goreng, satay and babi guling, plus fresh tropical fruit.',
                'budget_level' => 'budget', 'cost_index' => 40, 'climate' => 'tropical',
                'best_months' => [4, 5, 6, 7, 8, 9, 10],
                'themes' => ['beach', 'nature', 'adventure', 'art'],
                'safety_rating' => 4, 'latitude' => -8.4095, 'longitude' => 115.1889,
                'attractions' => [
                    ['name' => 'Tanah Lot Temple', 'tags' => ['nature', 'architecture'], 'popularity' => 0.80, 'latitude' => -8.6212, 'longitude' => 115.0868],
                    ['name' => 'Uluwatu Temple and Cliffs', 'tags' => ['nature', 'adventure', 'architecture'], 'popularity' => 0.80, 'latitude' => -8.8291, 'longitude' => 115.0849],
                    ['name' => 'Tegallalang Rice Terraces', 'tags' => ['nature'], 'popularity' => 0.75, 'latitude' => -8.4312, 'longitude' => 115.2793],
                    ['name' => 'Kuta Beach', 'tags' => ['beach', 'nightlife'], 'popularity' => 0.70, 'latitude' => -8.7184, 'longitude' => 115.1686],
                ],
            ],
        ];

        foreach ($destinations as $row) {
            $attractions = $row['attractions'];
            unset($row['attractions']);

            $destination = Destination::updateOrCreate(
                ['name' => $row['name'], 'country' => $row['country']],
                $row
            );

            $destination->attractions()->delete();
            $destination->attractions()->createMany($attractions);
        }
    }
}