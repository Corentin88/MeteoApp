<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class MeteoController extends AbstractController
{
    private HttpClientInterface $client;
    private string $apiKey;
    private LoggerInterface $logger;
    private CacheInterface $cache;

    public function __construct(HttpClientInterface $client, LoggerInterface $logger, CacheInterface $cache)
    {
        $this->client = $client;
        $this->apiKey = $_ENV['RAPIDAPI_KEY']; // Charge la clé API depuis .env
        $this->logger = $logger;
        $this->cache = $cache;
    }

    #[Route('/meteo', name: 'meteo')]
    public function index(): Response
    {
        try {
            // Vérifier si les données sont en cache
            $weatherData = $this->cache->get('weather_data', function (ItemInterface $item) {
                // Si les données ne sont pas en cache, on effectue la requête API
                $item->expiresAfter(43200); // Expire après 1 heure (3600 secondes)

                // Coordonnées géographiques de Xertigny
                $latitude = 48.2719;
                $longitude = 6.4822;

                // Construire l'URL de la requête avec les paramètres
                $url = 'https://ai-weather-by-meteosource.p.rapidapi.com/daily?lat=' . $latitude . '&lon=' . $longitude . '&language=fr&units=auto';

                // Requête HTTP vers l'API avec le token dans les en-têtes
                $response = $this->client->request('GET', $url, [
                    'headers' => [
                        'x-rapidapi-host' => 'ai-weather-by-meteosource.p.rapidapi.com',
                        'x-rapidapi-key' => $this->apiKey,
                    ]
                ]);

                // Récupérer les données de la réponse
                return $response->toArray(); // Convertir la réponse en tableau
            });

            // Extraire les prévisions quotidiennes
            $dailyData = $weatherData['daily']['data'] ?? []; // Si 'data' existe, récupérer les prévisions
            $todayWeather = $dailyData[0] ?? null;

            // Format heure Française
            $formatter = new \IntlDateFormatter(
                'fr_FR',  // Locale en français
                \IntlDateFormatter::LONG,  // Format long (ex : lundi 1 mars 2025)
                \IntlDateFormatter::NONE   // Pas d'heure affichée
            );

            // Formatage de la date pour todayWeather
            $todayDate = new \DateTime($todayWeather['day'] ?? 'N/A');
            $todayWeather['formattedDay'] = $formatter->format($todayDate);

            // Formatage des dates pour dailyData
            foreach ($dailyData as &$day) {
                $date = new \DateTime($day['day'] ?? 'N/A');
                $day['formattedDay'] = $formatter->format($date);  // Ajouter la date formatée à chaque jour
            }

            // Définir les variables à passer à la vue
            $day = $todayWeather['formattedDay'];
            $weather = $todayWeather['weather'] ?? 'N/A';  // Vérifie que 'weather' est bien dans les données
            $icon = $todayWeather['icon'] ?? '';  // Vérifie que 'icon' est bien dans les données
            $summary = $todayWeather['summary'] ?? 'N/A';
            $temperature = $todayWeather['temperature'] ?? 'N/A';
            $temperatureMin = $todayWeather['temperature_min'] ?? 'N/A';
            $temperatureMax = $todayWeather['temperature_max'] ?? 'N/A';
            $feelsLike = $todayWeather['feels_like'] ?? 'N/A';
            $windSpeed = $todayWeather['wind']['speed'] ?? 'N/A';  // Données sur le vent dans 'wind'
            $precipitationType = $todayWeather['precipitation']['type'] ?? 'N/A';  // Type de précipitation
            $probabilityPrecipitation = $todayWeather['probability']['precipitation'] ?? 'N/A';  // Probabilité de précipitation
            $probabilityStorm = $todayWeather['probability']['storm'] ?? 'N/A';  // Probabilité de tempête
            $probabilityFreeze = $todayWeather['probability']['freeze'] ?? 'N/A';  // Probabilité de gel
            $humidity = $todayWeather['humidity'] ?? 'N/A';


            // Passer les données à la vue Twig
            return $this->render('meteo/index.html.twig', [
                'day' => $day,
                'weather' => $weather,
                'icon' => $icon,  // Assurez-vous que cette variable est bien passée à la vue
                'summary' => $summary,
                'temperature' => $temperature,
                'temperatureMin' => $temperatureMin,
                'temperatureMax' => $temperatureMax,
                'feelsLike' => $feelsLike,
                'windSpeed' => $windSpeed,
                'precipitationType' => $precipitationType,
                'probabilityPrecipitation' => $probabilityPrecipitation,
                'probabilityStorm' => $probabilityStorm,
                'probabilityFreeze' => $probabilityFreeze,
                'humidity' => $humidity,
                'dailyData' => array_slice($dailyData, 1, 7), // Météo pour les jours suivants
            ]);


           


        } catch (\Exception $e) {
            // Log l'erreur en cas de problème avec l'API
            $this->logger->error('Erreur API : ' . $e->getMessage());

            return new Response('Erreur lors de la récupération des données météo : ' . $e->getMessage(), 500);
        }
    }
}
