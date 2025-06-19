<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class FixNeighborhoodUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-neighborhood-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update neighborhood on users table';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fixNeighborhoods = [
            'Abolição' => ['abolicao', 'ABOLIÇÃO', 'ABOLICAO'],
            'Acari' => ['acari', 'ACARI'],
            'Água Santa' => ['agua santa', 'ÁGUA SANTA'],
            'Alto da Boa Vista' => ['alto da boa vista', 'ALTO DA BOA VISTA'],
            'Anchieta' => ['anchieta', 'ANCHIETA'],
            'Andaraí' => ['andarai', 'ANDARAÍ'],
            'Anil' => ['anil', 'ANIL'],
            'Bancários' => ['bancarios', 'BANCÁRIOS'],
            'Bangu' => ['bangu', 'BANGU'],
            'Barra da Tijuca' => ['barra da tijuca', 'barra tijuca', 'BARRA DA TIJUCA'],
            'Barra de Guaratiba' => ['barra de guaratiba', 'barra guaratiba', 'GUARATIBA'],
            'Barra Olímpica' => ['barra olimpica', 'barra olímpica'],
            'Barros Filho' => ['barros filho', 'BARROS FILHO'],
            'Benfica' => ['benfica', 'BENFICA'],
            'Bento Ribeiro' => ['bento ribeiro', 'BENTO RIBEIRO'],
            'Bonsucesso' => ['bonsucesso', 'BONSUCCESSO'],
            'Botafogo' => ['botafogo', 'BOTAFOGO'],
            'Brás de Pina' => ['Bras de pina', 'bras de pina', 'brás de pina'],
            'Cacuia' => ['cacuia', 'CACUIA'],
            'Caju' => ['caju', 'CAJU'],
            'Camorim' => ['camorim', 'CAMORIM'],
            'Campinho' => ['campinho', 'CAMPINHO'],
            'Campo dos Afonsos' => ['campo dos afonsos', 'campo dos afonso'],
            'Campo Grande' => ['Campo grande', 'campo grande', 'CAMPO GRANDE'],
            'Cascadura' => ['cascadura', 'CASCADURA'],
            'Catete' => ['catete', 'CATETE'],
            'Catumbi' => ['catumbi', 'CATUMBI'],
            'Cavalcanti' => ['cavalcanti', 'CAVALCANTI'],
            'Cocotá' => ['cocota', 'COCOTÁ'],
            'Complexo do Alemão' => ['complexo do alemao', 'alemão'],
            'Cosme Velho' => ['cosme velho', 'COSME VELHO'],
            'Cosmos' => ['cosmos', 'COSMOS'],
            'Copacabana' => ['copacabana', 'Copacabana RJ', 'Copacabana Rj', 'Copacabana rj', 'COPACABANA'],
            'Curicica' => ['curicica', 'CURICICA'],
            'Del Castilho' => ['del castilho', 'DEL CASTILHO'],
            'Deodoro' => ['deodoro', 'DEODORO'],
            'Encantado' => ['encantado', 'ENCANTADO'],
            'Engenheiro Leal' => ['engenheiro leal', 'LEAL'],
            'Engenheiro Paulo de Frontin' => ['paulo de frontin'],
            'Engenho da Rainha' => ['engenho da rainha', 'RAINHA'],
            'Engenho de Dentro' => ['engenho de dentro', 'DENTRO'],
            'Engenho Novo' => ['engenho novo', 'NOVO'],
            'Estácio' => ['estacio', 'estácio'],
            'Freguesia (Jacarepaguá)' => ['freguesia de jacarepaguá', 'freguesia jacarepagua'],
            'Freguesia (Ilha do Governador)' => ['freguesia ilha do governador'],
            'Flamengo' => ['flamengo', 'FLAMENGO'],
            'Galeão' => ['galeao', 'GALEÃO'],
            'Gamboa' => ['gamboa', 'GAMBOA'],
            'Gardênia Azul' => ['gardenia azul', 'GARDÊNIA AZUL'],
            'Gávea' => ['gavea', 'GÁVEA'],
            'Glória' => ['gloria', 'GLÓRIA'],
            'Grajaú' => ['grajau', 'GRAJAÚ'],
            'Grumari' => ['grumari', 'GRUMARI'],
            'Guadalupe' => ['guadalupe', 'GUADALUPE'],
            'Guaratiba' => ['guaratiba', 'GUARATIBA'],
            'Higienópolis' => ['higienopolis', 'HIGIENÓPOLIS'],
            'Honório Gurgel' => ['honorio gurgel', 'HONÓRIO GURGEL'],
            'Humaitá' => ['humaita', 'HUMAITÁ'],
            'Inhaúma' => ['inhauma', 'INHAÚMA'],
            'Inhoaíba' => ['inhoaiba', 'INHOAÍBA'],
            'Irajá' => ['iraja', 'IRAJÁ'],
            'Itanhangá' => ['itanhanga', 'ITANHANGÁ'],
            'Jacaré' => ['jacare', 'JACARÉ'],
            'Jacarepaguá' => ['jacarepagua', 'JACAREPAGUÁ'],
            'Jacarezinho' => ['jacarezinho'],
            'Jabour' => ['jabour'],
            'Jardim América' => ['jardim américa'],
            'Jardim Botânico' => ['jardim botanico', 'JARDIM BOTÂNICO'],
            'Jardim Carioca' => ['jardim carioca'],
            'Jardim Guanabara' => ['jardim guanabara'],
            'Jardim Sulacap' => ['jardim sulacap'],
            'Joá' => ['joa', 'JOÁ'],
            'Lagoa' => ['lagoa', 'LAGOA'],
            'Laranjeiras' => ['laranjeiras', 'LARANJEIRAS'],
            'Leblon' => ['leblon', 'LEBLON'],
            'Leme' => ['leme', 'LEME'],
            'Lins de Vasconcelos' => ['lins de vasconcelos'],
            'Madureira' => ['madureira', 'MADUREIRA'],
            'Magalhães Bastos' => ['magalhães bastos'],
            'Mangueira' => ['mangueira', 'MANGUEIRA'],
            'Maria da Graça' => ['maria da graça'],
            'Méier' => ['meier', 'MEIER'],
            'Moneró' => ['monero', 'MONERÓ'],
            'Manguinhos' => ['manguinhos'],
            'Maracanã' => ['maracana', 'MARACANÃ'],
            'Maré' => ['mare', 'MARÉ'],
            'Marechal Hermes' => ['marechal hermes'],
            'Olaria' => ['olaria', 'OLARIA'],
            'Oswaldo Cruz' => ['oswaldo cruz'],
            'Paquetá' => ['paqueta', 'PAQUETÁ'],
            'Parada de Lucas' => ['parada de lucas'],
            'Parque Anchieta' => ['parque anchieta'],
            'Parque Colúmbia' => ['parque colúmbia', 'parque columbia'],
            'Pavuna' => ['pavuna', 'PAVUNA'],
            'Pechincha' => ['pechincha', 'PECHINCHA'],
            'Pedra de Guaratiba' => ['pedra de guaratiba'],
            'Penha' => ['penha', 'PENHA'],
            'Penha Circular' => ['penha circular'],
            'Piedade' => ['piedade'],
            'Pilares' => ['pilares'],
            'Pi**tangueiras**' => ['pitangueiras'], // ensure correct key
            'Portuguesa' => ['portuguesa'],
            'Praça da Bandeira' => ['praca da bandeira'],
            'Praça Seca' => ['praca seca'],
            'Praia da Bandeira' => ['praia da bandeira'],
            'Quintino Bocaiúva' => ['quintino bocaiúva', 'Quintino Bocaiuva'],
            'Ramos' => ['ramos'],
            'Realengo' => ['realengo', 'REALENGO'],
            'Recreio dos Bandeirantes' => ['recreio dos bandeirantes', 'recreio'],
            'Riachuelo' => ['riachuelo'],
            'Ribeira' => ['ribeira'],
            'Ricardo de Albuquerque' => ['ricardo de albuquerque'],
            'Rio Comprido' => ['rio comprido'],
            'Rocha' => ['rocha'],
            'Rocha Miranda' => ['rocha miranda'],
            'Rocinha' => ['rocinha'],
            'Sampaio' => ['sampaio'],
            'Santa Cruz' => ['santa cruz'],
            'Santa Teresa' => ['santa teresa'],
            'Santíssimo' => ['santissimo', 'SANTÍSSIMO'],
            'Santo Cristo' => ['santo cristo'],
            'São Conrado' => ['sao conrado', 'SÃO CONRADO'],
            'São Cristóvão' => ['sao cristovao', 'SÃO CRISTÓVÃO'],
            'São Francisco Xavier' => ['sao francisco xavier'],
            'Saúde' => ['saude', 'SÁUDE'],
            'Senador Camará' => ['senador camara'],
            'Senador Vasconcelos' => ['senador vasconcelos'],
            'Sepetiba' => ['sepetiba'],
            'Tanque' => ['tanque'],
            'Taquara' => ['taquara'],
            'Tauá' => ['taua'],
            'Tijuca' => ['tijuca', 'TIJUCA'],
            'Todos os Santos' => ['todos os santos'],
            'Tomás Coelho' => ['tomas coelho'],
            'Tubiacanga' => ['tubiacanga'],
            'Turiaçu' => ['turiacu'],
            'Urca' => ['urca'],
            'Vargem Grande' => ['vargem grande'],
            'Vargem Pequena' => ['vargem pequena'],
            'Vasco da Gama' => ['vasco da gama'],
            'Vaz Lobo' => ['vaz lobo'],
            'Vicente de Carvalho' => ['vicente de carvalho'],
            'Vidigal' => ['vidigal'],
            'Vigário Geral' => ['vigario geral'],
            'Vila da Penha' => ['vila da penha'],
            'Vila Isabel' => ['vila isabel'],
            'Vila Kosmos' => ['vila kosmos'],
            'Vila Militar' => ['vila militar'],
            'Vila Valqueire' => ['vila valqueire'],
            'Vista Alegre' => ['vista alegre'],
            'Zumbi' => ['zumbi'],
        ];

        $users = User::all();
        $fixed = 0;

        foreach ($users as $user) {
            if (! $user->neighborhood) {
                continue;
            }

            $neighborhoodOriginal = strtolower(trim($user->neighborhood));

            foreach ($fixNeighborhoods as $correctNeighborhood => $possible) {
                if (in_array($neighborhoodOriginal, array_map('strtolower', $possible))) {
                    if ($user->neighborhood !== $correctNeighborhood) {
                        $user->neighborhood = $correctNeighborhood;
                        $user->save();
                        $fixed++;
                    }
                    break;
                }
            }
        }

        $this->info("Fixed neighborhoods: {$fixed}");
    }
}
