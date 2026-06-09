<?php
/**
 * MapsAPI — Wrapper 100% gratuito
 * ─────────────────────────────────
 * • Mapa:          Leaflet.js  + OpenStreetMap tiles  (grátis, sem key)
 * • Autocomplete:  Nominatim API  (OpenStreetMap)     (grátis, sem key)
 * • Rotas:         OSRM API       (Project OSRM)      (grátis, sem key)
 * • Geocoding:     Nominatim API  (OpenStreetMap)     (grátis, sem key)
 */

require_once __DIR__ . '/config.php';

class MapsAPI {

    private string $nominatim;
    private string $osrm;
    private string $ua;

    public function __construct() {
        $this->nominatim = NOMINATIM_URL;
        $this->osrm      = OSRM_URL;
        $this->ua        = NOMINATIM_UA;
    }

    // ─────────────────────────────────────────────────────────
    //  Geocodificação: endereço → lat/lng  (Nominatim)
    // ─────────────────────────────────────────────────────────
    public function geocodificar(string $endereco): ?array {
        $url = $this->nominatim . '/search?' . http_build_query([
            'q'              => $endereco,
            'format'         => 'jsonv2',
            'limit'          => 1,
            'addressdetails' => 1,
            'countrycodes'   => 'br',
        ]);
        $data = $this->_get($url);
        if (!$data || empty($data)) return null;
        $r = $data[0];
        return [
            'lat'                => (float) $r['lat'],
            'lng'                => (float) $r['lon'],
            'endereco_formatado' => $r['display_name'],
            'place_id'           => $r['place_id'],
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  Autocomplete de endereços  (Nominatim)
    // ─────────────────────────────────────────────────────────
    public function autocompletar(string $input): array {
        if (strlen($input) < 3) return [];

        $url = $this->nominatim . '/search?' . http_build_query([
            'q'            => $input . ', Brasil',
            'format'       => 'jsonv2',
            'limit'        => 6,
            'countrycodes' => 'br',
        ]);
        $data = $this->_get($url);
        if (!$data) return [];

        return array_map(fn($r) => [
            'descricao' => $r['display_name'],
            'place_id'  => $r['place_id'],
            'lat'        => (float) $r['lat'],
            'lng'        => (float) $r['lon'],
        ], $data);
    }

    // ─────────────────────────────────────────────────────────
    //  Calcular rota  (OSRM — real por estradas)
    //  $waypoints = array de ['lat'=>..., 'lng'=>...]
    // ─────────────────────────────────────────────────────────
    public function calcular_rota(array $origem, array $destino, array $waypoints = []): ?array {
        // Montar lista de coordenadas: origem, [waypoints], destino
        $coords   = [$origem, ...$waypoints, $destino];
        $coordStr = implode(';', array_map(fn($c) => "{$c['lng']},{$c['lat']}", $coords));

        $url = $this->osrm . "/route/v1/driving/{$coordStr}?" . http_build_query([
            'overview'    => 'full',
            'geometries'  => 'geojson',
            'steps'       => 'false',
            'annotations' => 'false',
        ]);

        $data = $this->_get($url);
        if (!$data || ($data['code'] ?? '') !== 'Ok' || empty($data['routes'])) return null;

        $rota = $data['routes'][0];
        $distM = $rota['distance'];  // metros
        $durS  = $rota['duration'];  // segundos
        $km    = $distM / 1000;

        // Geometry GeoJSON → array de [lat, lng] para Leaflet
        $points = array_map(
            fn($c) => [$c[1], $c[0]],  // OSRM retorna [lng, lat], Leaflet quer [lat, lng]
            $rota['geometry']['coordinates']
        );

        $consumo_litros    = $km / CONSUMO_PADRAO_KM_L;
        $custo_combustivel = $consumo_litros * PRECO_MEDIO_DIESEL;
        $custo_pedagio     = $km * CUSTO_PEDAGIO_KM;

        return [
            'distancia_m'           => (int) $distM,
            'distancia_km'          => round($km, 2),
            'duracao_s'             => (int) $durS,
            'duracao_formatada'     => $this->_formatar_duracao((int) $durS),
            'polyline_points'       => $points,          // array de [lat,lng]
            'consumo_litros_est'    => round($consumo_litros, 2),
            'custo_combustivel_est' => round($custo_combustivel, 2),
            'custo_pedagio_est'     => round($custo_pedagio, 2),
            'custo_total_est'       => round($custo_combustivel + $custo_pedagio, 2),
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  Utilitários privados
    // ─────────────────────────────────────────────────────────
    private function _get(string $url): ?array {
        $ctx = stream_context_create(['http' => [
            'timeout' => 8,
            'header'  => "User-Agent: {$this->ua}\r\nAccept: application/json\r\n",
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) return null;
        return json_decode($body, true);
    }

    private function _formatar_duracao(int $seg): string {
        $h = intdiv($seg, 3600);
        $m = intdiv($seg % 3600, 60);
        return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
    }
}
