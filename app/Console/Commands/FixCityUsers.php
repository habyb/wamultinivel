<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class FixCityUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-city-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update city on users table';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fixedCities = [
            'Angra dos Reis' => [
                'angra dos reis',
                'Angra Dos Reis',
                'ANGRA DOS REIS',
                'angraDosReis'
            ],
            'Aperibé' => [
                'aperibé',
                'aperibe',
                'APERIBÉ',
                'APERIBE'
            ],
            'Araruama' => [
                'araruama',
                'ARARUAMA',
                'Araruama Região dos Lagos',
            ],
            'Areal' => [
                'areal',
                'AREAL'
            ],
            'Armação dos Búzios' => [
                'armacao dos buzios',
                'armação dos búzios',
                'ARMAÇÃO DOS BÚZIOS',
                'buzios',
                'Armacao dos Búzios',
                'Armacão dos Búzios',
                'Armaçao dos Búzios',
                'Armação do Búzios',
                'Armação dos Buzios',
                'Buzios Cem Braças',
                'Búzios',
            ],
            'Arraial do Cabo' => [
                'arraial do cabo',
                'ARRAIAL DO CABO'
            ],
            'Barra do Piraí' => [
                'barra do piraí',
                'barra do pirai',
                'BARRA DO PIRAÍ',
                'Barra do pirai',
                'BARRA DO PIRAI',
                'Barra do Pirai !',
                'Barra do Piraí e Rio das flores',
                'Barra do Piraí - RJ',
                'Barra do Piraí -RJ',
                'Barra do Piraí RJ',
                'Barra do Piraí r.j',
                'Barra do Piraí-RJ',
                'Barra do Piraí.',
                'Barrra do Piraí',
                'Barra do pirai R.J',
                'Barra fo pirai',
                'Barão do Piraí',
                'Bara do Piraí', 
                'Bara do pirai', 
                'Barta do Piraí', 
                'Barta do pirai', 
                'Barrs do Piraí', 
                'Barrs do pirai', 
                'Barra di Piraí', 
                'Barra di pirai', 
                'Barra dp Piraí', 
                'Barra dp pirai', 
                'Barra so Piraí', 
                'Barra so pirai', 
                'Barra do Porai', 
                'Barra do Pitai',
                'Barrado Piraí', 
                'Barrado Pirai', 
                'Barra doPirai', 
                'Barra Piraí', 
                'Barra Pirai', 
                'Barra de Piraí', 
                'Barra de pirai', 
                'Barra da Piraí', 
                'B. do Piraí', 
                'B do Pirai', 
                'Barr do Piraí',
                'Barra do Piraí / RJ', 
                'Barra do pirai/rj', 
                'Barra do Piraí (RJ)', 
                'Barra do pirai (rj)', 
                'Barra do pirai-rj', 
                'Barra do pirai, rj', 
                'Barra do pirai , rj', 
                'Barra do Piraí  RJ',
                'Barra do Pirahy', 
                'Barra do Pirahi',
                'Barão de Piraí',
                'Ipiabas',
                'Ipiabas Barra do Piraí',
                'Barra do Piraî',
                'Barra do Pirar',
                'Barra do Paraí',
                'Barra do Pira',
                'Barra do Piria',
                'Barra do Pirau',
                'Barro do Piraí',
                'Barra do PIraí',
                'Barra do Piraí Melhorar Asfalto',
                'Barra do Pirai Bairro Boca do Mato',
                'Barra do Piraí Estado do Rio de Janeiro',
                'Barra do Pirain',
                'Barra D Piraí',
                'Barra Só Pirai',
            ],
            'Barra Mansa' => [
                'barra mansa',
                'BARRA MANSA',
                'Barra Mansa, Siderlandia',
                'Barra Mansa.',
                'BarraMansa',
                'Barra',
                'Barra Manda',
                'Barra Nansa',
                'Barra Mansa Ré',
                'Barra Massa',
            ],
            'Belford Roxo' => [
                'belford roxo',
                'BELFORD ROXO',
                'belford roxo RJ centro',
                'Belford',
                'Berford Roxo',
                'Rio de Janeiro Belford Roxo',
                'Belford-Roxo',
                'Bel Roxo',
            ],
            'Bom Jardim' => [
                'bom jardim',
                'BOM JARDIM'
            ],
            'Bom Jesus do Itabapoana' => [
                'bom jesus do itabapoana',
                'bom jesus do ita',
                'Carabuçu Bom Jesus do Itabapoana',
                'Bom Jesus Itabapoana',
            ],
            'Cabo Frio' => [
                'cabo frio',
                'CABO FRIO',
                'Unamar- Cabo Frio',
            ],
            'Cachoeiras de Macacu' => [
                'Cachoeiras de macacu',
                'cachoeiras de macacu',
                'CACHOEIRAS DE MACACU',
                'Cachoeiras de Macacu.',
                'Cachoeiras Macacu',
                'Cachoeiras de Macau',
                'Cachoeiras de  Macacu',
                'Papucaia-Cachoeiras de Macacu',
                'Cachoeira de Macacu',
                'Cachoeira de Macau'
            ],
            'Campos dos Goytacazes' => [
                'Campos dos Goitacazes',
                'Campos dos goytacazes',
                'campos dos goytacazes',
                'CAMPOS DOS GOYTACAZES',
                'campos goytacazes'
            ],
            'Cantagalo' => [
                'cantagalo',
                'CANTAGALO'
            ],
            'Cardoso Moreira' => [
                'Cardoso moreira',
                'cardoso moreira',
                'CARDOso moreira'
            ],
            'Casimiro de Abreu' => [
                'casimiro de abreu',
                'CASIMIRO DE ABREU'
            ],
            'Comendador Levy Gasparian' => [
                'Comendador levy gasparian',
                'comendador levy gasparian'
            ],
            'Conceição de Macabu' => [
                'conceição de macabu',
                'concepcao de macabu'
            ],
            'Cordeiro' => [
                'cordeiro',
                'CORDEIRO'
            ],
            'Duas Barras' => [
                'duas barras',
                'DUAS BARRAS'
            ],
            'Duque de Caxias' => [
                'duque de caxias',
                'DUQUE DE CAXIAS',
                'caxias',
                'Rio de Janeiro - Duque de Caxias',
            ],
            'Engenheiro Paulo de Frontin' => [
                'engenheiro paulo de frontin',
                'Frontin'
            ],
            'Guapimirim' => [
                'guapimirim',
                'GUAPIMIRIM'
            ],
            'Iguaba Grande' => [
                'iguaba grande',
                'iguaba',
            ],
            'Itaboraí' => [
                'itaboraí',
                'ITABORAI',
                'ITABORAÍ'
            ],
            'Itaguaí' => [
                'itaguaí',
                'itaguai',
                'ITAGUAÍ',
                'Itaguaí Rio de Janeiro',
            ],
            'Itaocara' => [
                'itaocara',
                'ITAOCARA'
            ],
            'Itaperuna' => [
                'itaperuna',
                'ITAPERUNA'
            ],
            'Itatiaia' => [
                'itatiaia',
                'ITATIAIA'
            ],
            'Japeri' => [
                'japeri',
                'JAPERI'
            ],
            'Laje do Muriaé' => [
                'laje do muriaé',
                'laje do muriae'
            ],
            'Macaé' => [
                'Macaé RJ',
                'Macaé rj',
                'macaé',
                'macae'
            ],
            'Macuco' => [
                'macuco',
                'MACUCO'
            ],
            'Magé' => [
                'magé',
                'mage',
                'Magé-RJ',
                'Magé-Rj',
                'Magé-rj',
                'Mage-RJ',
                'Mage-Rj',
                'Mage-rj',
                'Magé Rj',
                'Magé rj',
                'Mage RJ',
                'Mage Rj',
                'Mage rj'
            ],
            'Mangaratiba' => [
                'mangaratiba',
                'MANGARATIBA',
                'Muriqui (Mangaratiba RJ)',
                'Managratiba',
            ],
            'Maricá' => [
                'maricá',
                'marica'
            ],
            'Mendes' => [
                'mendes',
                'MENDES'
            ],
            'Mesquita' => [
                'mesquita',
                'MESQUITA'
            ],
            'Miguel Pereira' => [
                'miguel pereira'
            ],
            'Miracema' => [
                'miracema',
                'Miracema RJ'
            ],
            'Natividade' => [
                'natividade'
            ],
            'Nilópolis' => [
                'nilópolis',
                'nilopolis'
            ],
            'Niterói' => [
                'niterói',
                'niteroi'
            ],
            'Nova Friburgo' => [
                'Nova friburgo',
                'nova friburgo',
                'Nf friburgo',
                'Nova Fraiburgo',
                'Nv Friburgo',
                'N F',
                'Cidade Nova Friburgo',
                'Friburgo',
                'N Friburgo',
                'Nava Friburgo',
                'Niva Friburgo',
                'Nossa Friburgo',
                'Nov Friburgo',
                'Rj Nova Friburgo',
                'Npva Friburgo',
                'Novo Friburgo',
                'Nova Friburgo Y',
                'Nova Friburgo R J',
                'Nova Friburgo Estado do Rio de Janeiro',
            ],
            'Nova Iguaçu' => [
                'nova iguaçu',
                'nova iguacu',
                'Nova iguacu',
                'Nova iguaçu',
                'Novo Iguaçu',
                'Nova Iguaçú',
                'Sou de Nova Iguaçu',
                'Jardim Paraíso Nova Iguaçu',
                'Nove Iguaçu',
                'Nova Iguaçu Ipiranga',
                'Nova Iguaçu Cabuçu',
            ],
            'Paracambi' => [
                'paracambi'
            ],
            'Paraíba do Sul' => [
                'paraíba do sul',
                'paraiba do sul'
            ],
            'Paraty' => [
                'paraty'
            ],
            'Paty do Alferes' => [
                'paty do alferes'
            ],
            'Petrópolis' => [
                'petrópolis',
                'petropolis',
                'Petropolis',
                'Petrópolis RJ',
                'Petrópolis Rj'
            ],
            'Pinheiral' => [
                'pinheiral'
            ],
            'Piraí' => [
                'piraí',
                'pirai',
                'Pirai Rio de Janeiro',
            ],
            'Porciúncula' => [
                'porciúncula',
                'porcioncula'
            ],
            'Porto Real' => [
                'porto real'
            ],
            'Quatis' => [
                'quatis'
            ],
            'Queimados' => [
                'queimados',
                'Queimados.'
            ],
            'Quissamã' => [
                'quissamã',
                'Quissama',
                'quissama'
            ],
            'Resende' => [
                'resende'
            ],
            'Rio Bonito' => [
                'Rio bonito',
                'rio bonito',
                'Rio Bonito- RJ'
            ],
            'Rio Claro' => [
                'Rio claro',
                'rio claro',
                'Lidice Segundo Distrito de Rio Claro',
                'Lídice',
                'Lidice',
            ],
            'Rio das Flores' => [
                'Rio das flores',
                'Rio das Flôres',
                'rio das flores',
                'Rio de Florês',
                'Rio das Florês',
            ],
            'Rio das Ostras' => [
                'Rio das ostras',
                'rio das ostras',
                'Rio das Ostra',
                'Rio das Outras',
            ],
            'Rio de Janeiro' => [
                'Rio de janeiro',
                'rio de janeiro',
                'RIO DE JANEIRO',
                'Río de Janeiro',
                'Río de janeiro',
                'Rio dd janeiro',
                'Rio de Jnairo',
                'Ri de Janeiro',
                'Rio de janeiro - rio de janeiro',
                'RJ',
                'rj',
                'Rj',
                'Irajá - RJ',
                'Lagoa Santa',
                'Cavalcante',
                'Campo Grande. RJ',
                'rio de janeiro, campo grande',
                'Campo Grande RJ',
                'Campo Grande Rio de Janeiro',
                'Campo Grande - Rio de Janeiro',
                'Campo grande',
                'Rio  de  Janeiro',
                'Rio  de Janeiro',
                'Rio Janeiro',
                'Rio de Janeio',
                'Rio de Janeiro.',
                'Rio de janeiro.',
                'Rio de janeiro....',
                'Rio de Jan',
                'rio',
                'Vila da penha',
                'Vila da Penha',
                'Rio e teresopolis',
                'Rio de Janeiro/Bairro C.Grande',
                'Rio de Janeiro Campo Grande',
                'Rio de janeiro, RJ',
                'Pavuna',
                'Rio de Janeiro urucania.',
                'Rj, santa cruz',
                'Rio de Janeiro Santa Cruz',
                'Rio de.janeiro',
                'Moro em Bangu vila kennedy',
                'Realengo',
                'Ria de janeiro',
                'Rio de janeiro/RJ',
                'Rio de jeneiro',
                'Rio de Janeiro/ campo grande',
                'Rio d3 janeiro',
                'Rio. De janeiro',
                'Rio de janeiro Bangu',
                'Realengo Rj',
                'Vargem Grande, RJ',
                'Inhoaiba',
                'Rio de Janeiro Capital',
                'Rio de Janeiri',
                'Rio de Janeiro Penha',
                'Penha',
                'Vargem Grande,  RJ',
                'Rio de Janeiro cordovil',
                'Rio Dejaneiro',
                'Rio de Janeieo',
                'Rio de Janeito',
                'Rio de Janeuro',
                'Rio de Janieiro',
                'Rio do Janeiro',
                'Ro do Janeiro',
                'Ro de Janeiro',
                'Rio da Janeiro',
                'Rio de Janeiro Queimados',
                'Rio de Janeiro Nova Iguaçu',
                'Tio de Janeiro',
                'Rio de Janeiro-',
                'Rio de Laneiro',
                'Rio de Janeiro Inhoaiba',
                'Mangueira Rio de Janeiro',
                'Riio de Janeiro',
                'Rio de Janiro',
                'Rio de Januário',
                'Rio de Azevedo',
                'Rio de Jane',
                'Rio de Janeiroo',
                'Rio de Janejro',
                'Rio de Já',
                'Rio de Janeiro Jacaré',
                'Rio de Janeiro Manquinhos',
                'Rio de Janeiro Realengo',
                'Rio de Janeiro Sepetiba',
                'Rio de Jaú Euro',
                'Rio de Janeiro Ilha do Governador',
                'Rio de Janeiro - Ilha do Governador',
                'Riode Janeiro',
                'Rii de Janeiro',
                'Fio de Janeiro',
                'Ruo de Janeiro',
                'Rj Campo Grande',
                'Rio Campo Grande',
                'Rui de Janeiro',
                'Rui O de Janeiro',
                'Tudo de Janeiro',
                'Eio de Janeiro',
                'Campo Grande Rio',
                'Rio Dejjaneiro',
                'Rio de Janiero',
                'Rio de Jansiro',
                'Rio de Janeiro Manguinhos',
                'Rio de Janeiro The',
                'Rio de Janeiro Bairro Manguinhos',
                'Rio de Janeiro Rio de Janeiro',
                'Rioe',
                'Rio Dr Janeiro',
                'Rio de Faneiri',
                'Rio de Janeiero',
                'Rio de Janeira',
                'Rio de Janerio',
                'Rio de Oliveira',
                'Rio de Janeiro Manguinho',
                'Rio de Janeiro - Manguinhos',
                'Rio de Janeiro Bairro Pilares',
                'Rio de Janeiro Bairro Quintino',
                'Rio de Janeiro Jardim América',
                'Rio de Janeiro No Bairro de Benfica Em Manguinhos',
                'Rio-De-Janeiro',
                'Riodejaneiro',
                'Vanessa Rio de Janeiro',
                'Bonsucesso Rio de Janeiro',
                'Rio D Janeiro',
                'Rioo de Janeiro',
                'Rio de',
            ],
            'Santa Maria Madalena' => [
                'Santa maria madalena',
                'Santa Maria madalena',
                'santa maria madalena'
            ],
            'Santo Antônio de Pádua' => [
                'santo antônio de pádua',
                'santo antonio de padua'
            ],
            'São Fidélis' => [
                'são fidélis',
                'sao fidelis',
                'São  fidelis'
            ],
            'São Francisco de Itabapoana' => [
                'são francisco de itabapoana',
                'sao francisco de itabapoana'
            ],
            'São Gonçalo' => [
                'São gonçalo',
                'São Gonsalo',
                'São goncalo',
                'São Goncalo',
                'são gonçalo',
                'sao goncalo',
                'São Gonçalo RJ',
                'São Gonçalo/RJ',
                'São Gonçalo Rio de janeiro',
                'S G',
                'Sao Gonçalo',
                'São Gonçalo Estado do Rio',
            ],
            'São João da Barra' => [
                'São joão da barra',
                'São João da barra',
                'Sao Joao da Barra',
                'são joão da barra',
                'sao joao da barra'
            ],
            'São João de Meriti' => [
                'são joão de meriti',
                'sao joao de meriti',
                'São João'
            ],
            'São José de Ubá' => [
                'são josé de ubá',
                'sao jose de uba'
            ],
            'São José do Vale do Rio Preto' => [
                'são josé do vale do rio preto',
                'sao jose do vale do rio preto',
                'São Jose do Vale do Rio Preto',
                'Sjvrp'
            ],
            'São Pedro da Aldeia' => [
                'são pedro da aldeia',
                'sao pedro da aldeia',
                'São Pedro da Aldeia  RJ',
                'São Pedro',
                'São Pedro de Apdeia',
                'Sao Pedro D Aldeia',
                'Sao Adro da Aldeia',
                'Sao Pedro da Aldeie',
                'São Pedro da Aldeia Rio de Janeiro',
                'São Pedro da Aldeia - Rio de Janeiro',
                'São Pedro da Aldeia- Rio de Janeiro',
                'São Pedro da Aldei',
                'São Pedro da Aldeia',
                'São Pedro da Aldeia A',
                'São Pedro da Aldeia Rio de Janeiro',
                'São Pedro Dá Aldeia',
                'São Pedro de Aldeia',
                'São Pedro do Aldeia',
                'São Pedro Daldeia',
                'Sou Pedro da Aldeia',
                'São Adro da Aldeia',
                'São Pedro Aldeia',
                'Nossa São Pedro da Aldeia',
                'São Pedro da A Aldeia',
                'Moro Em São Pedro da Aldeia',
                'S Pedro D Aldea',
                'São Pedro de Estado do Rio de Janeiro',
                'São Pedro da Aldeia Boa Vista',
                'Sao Pedro',
                'Sâo Pedro da Aldeia',
            ],
            'São Sebastião do Alto' => [
                'são sebastião do alto',
                'sao sebastiao do alto'
            ],
            'Sapucaia' => [
                'sapucaia'
            ],
            'Saquarema' => [
                'saquarema',
                'saqusrema'
            ],
            'Seropédica' => [
                'seropédica',
                'seropedica',
                'Seropédica RJ',
                'Seropedica RJ',
                'Seropédica/RJ',
                'Seropedica/RJ',
                'Seropédica Campo Lindi',
                'Rj Seropédica',
                'Rj- Seropedica',
                'Canto do Rio Seropédica',
                'Canto do Rio Seropedica',
                'Seropedica Bairro - Fazenda Caxias',
                'Serooedica',
            ],
            'Silva Jardim' => [
                'silva jardim'
            ],
            'Sumidouro' => [
                'sumidouro'
            ],
            'Tanguá' => [
                'Tangua',
                'tanguá',
                'tangua',
                'Tanguá-RJ'
            ],
            'Teresópolis' => [
                'Teresópolis rj',
                'Teresópolis RJ',
                'teresópolis',
                'teresopolis',
                'Taresopolis',
                'Teresópolis, Rj',
                'Teresópolis Estado do Rio',
                'Rafael Teresópolis',
                'Teresópolis Rio de Janeiro',
                'Rj Teresópolis',
                'Teresópolis Barra do Imbui',
                'Teresopolis Rio de Janeiro',
                'Teresópolis Estado do Rio de Janeiro',
                'Teres',
                'Teleresopolis',
                'Teresópolis Est do Rio Janeiro',
            ],
            'Trajano de Moraes' => [
                'trajano de moraes'
            ],
            'Três Rios' => [
                'três rios',
                'tres rios',
                'Três Rios RJ'
            ],
            'Valença' => [
                'Concevatoria',
                'Conservatória-Valença',
                'Valença.',
                'Valença RJ',
                'Valença Rj',
                'Valença. RJ',
                'Valença rj',
                'Valença/ RJ',
                'valença',
                'valenca',
                'Valença, RJ',
                'Valença-RJ',
                'Valença-Rj',
                'Valença - RJ',
                'Valenca Rj',
                'Valenca rj',
                'VALENÇA',
                'Valença/RJ.',
                'Santa Isabel do Rio Preto - Valença',
                'Valença E d R',
                'Valença Estado do Rio .',
                'Valença estado do Rio',
                'Valençarj',
                'Valença/RJ',
                'Valença/Rj',
                'Valença/rj',
                'Santa Isabel do rio preto',
                'Valença -rj',
                'Valença estado rj',
                'Valença ,interior do Rio de janeiro.',
                'Est Do Rio De Janeiro Valença',
                'Valença/ Santa Isabel do Rio preto!',
                'Valença ..',
                'Valença  RJ',
                'Barão de Juparanã',
                'Barão de juparanã',
                'Barão de juparana',
                'barão de juparana',
                'Barao de Juparanã',
                'Barao de juparanã',
                'BARÃO DE JUPARANA.',
                'Juparanã',
                'Barão de Juparana Valença',
                'Parapeúna',
                'Parapeuna',
                'parapeuna',
                'Conservatória',
                'Conservatoria',
                'conservatoria',
                'Valença  Estado do Rio',
                'Parapeúna Valença',
                'Santa Isabel',
                'Santa Isabel do Rio Preto Distrito de Valença',
                'Santa Isabel do Rio Preto Município de Valença',
                'Barão de Juparanã - Valença',
                'Barão de Juparanã Valença',
                'Conservatória - Valença',
                'Juoara',
                'Juparana',
                'Valença Estado do Rio de Janeiro',
                'Valença Mais Tenho Casa Também No Rio',
                'Elaine da Costa Eduardo Ramos Conservatória',
                'Valença Distrito Barão de Juparana',
                'Valença Rio de Janeiro',
                'Valença Bairro São Francisco',
                'Santa Isabel - Valença',
                'Valnça',
                'Parapeuna Valença',
                'Parapeuna Rj Paiolinho',
                'Valença R-J',
                'Valecia',
                'Valensa',
                'Valença Conservatória',
                'Valanca',
                'Vslenca',
                'Rio de Janeiro Valença',
                'Parapeuns Valença Bairro dos Bastos',
                'Valença Parapeùna',
                'Parapeúna - Valença',
                'Parapeuna - Valença',
                'Marquês de Valença',
                'Valencia',
                'Valença Est do Rio de Janeiro',
                'Santa Isabel do Rio Preto Valença',
                'Valeça',
                'Velença',
                'Vslença',
                'Parapena Valença',
                'Sou de Valença',
                'Conservatória- Valença',
                'Vaienca',
                'Vença',
                'Resende Volta Redonda Valença',
                'Fazenda Botafogo',
            ],
            'Varre-Sai' => [
                'varre-sai',
                'varre sai'
            ],
            'Vassouras' => [
                'vassouras',
                'vassoura',
                'Vassoura',
                'Vassouras - RJ',
                'Vassoura - RJ'
            ],
            'Volta Redonda' => [
                'volta redonda',
                'Volta Redonda - RJ'
            ],
            'São Paulo' => [
                'São paulo',
                'sao paulo',
                'sp',
                'Sao Paulo',
                'SP',
                'Sp',
                's.paulo'
            ],
            'Matias Barbosa' => [
                'Matias barbosa jf MG'
            ],
            'Santa Rita de Jacutinga' => [
                'Santa Rita de jacutinga'
            ]
        ];

        $users = User::all();
        $fixed = 0;

        foreach ($users as $user) {
            if (! $user->city) {
                continue;
            }

            $cityOriginal = strtolower(trim($user->city));

            foreach ($fixedCities as $correctCity => $possible) {
                if (in_array($cityOriginal, array_map('strtolower', $possible))) {
                    if ($user->city !== $correctCity) {
                        $user->city = $correctCity;
                        $user->save();
                        $fixed++;
                    }
                    break;
                }
            }
        }

        $this->info("Fixed cities: {$fixed}");
    }
}
