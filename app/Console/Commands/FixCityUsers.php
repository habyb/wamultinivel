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
            'Angra dos Reis' => ['angra dos reis', 'Angra Dos Reis', 'ANGRA DOS REIS', 'angraDosReis'],
            'Aperibé' => ['aperibé', 'aperibe', 'APERIBÉ', 'APERIBE'],
            'Araruama' => ['araruama', 'ARARUAMA'],
            'Areal' => ['areal', 'AREAL'],
            'Armação dos Búzios' => ['armacao dos buzios', 'armação dos búzios', 'ARMAÇÃO DOS BÚZIOS', 'buzios'],
            'Arraial do Cabo' => ['arraial do cabo', 'ARRAIAL DO CABO'],
            'Barra do Piraí' => ['barra do piraí', 'barra do pirai', 'BARRA DO PIRAÍ', 'Barra do pirai', 'BARRA DO PIRAI'],
            'Barra Mansa' => ['barra mansa', 'BARRA MANSA'],
            'Belford Roxo' => ['belford roxo', 'BELFORD ROXO'],
            'Bom Jardim' => ['bom jardim', 'BOM JARDIM'],
            'Bom Jesus do Itabapoana' => ['bom jesus do itabapoana', 'bom jesus do ita'],
            'Cabo Frio' => ['cabo frio', 'CABO FRIO'],
            'Cachoeiras de Macacu' => ['Cachoeiras de macacu', 'cachoeiras de macacu', 'CACHOEIRAS DE MACACU', 'Cachoeiras de Macacu.'],
            'Campos dos Goytacazes' => ['Campos dos Goitacazes', 'Campos dos goytacazes', 'campos dos goytacazes', 'CAMPOS DOS GOYTACAZES', 'campos goytacazes'],
            'Cantagalo' => ['cantagalo', 'CANTAGALO'],
            'Cardoso Moreira' => ['Cardoso moreira', 'cardoso moreira', 'CARDOso moreira'],
            'Casimiro de Abreu' => ['casimiro de abreu', 'CASIMIRO DE ABREU'],
            'Comendador Levy Gasparian' => ['Comendador levy gasparian', 'comendador levy gasparian'],
            'Conceição de Macabu' => ['conceição de macabu', 'concepcao de macabu'],
            'Cordeiro' => ['cordeiro', 'CORDEIRO'],
            'Duas Barras' => ['duas barras', 'DUAS BARRAS'],
            'Duque de Caxias' => ['duque de caxias', 'DUQUE DE CAXIAS', 'caxias'],
            'Engenheiro Paulo de Frontin' => ['engenheiro paulo de frontin'],
            'Guapimirim' => ['guapimirim', 'GUAPIMIRIM'],
            'Itaboraí' => ['itaboraí', 'itaboraí', 'ITABORAI', 'ITABORAÍ'],
            'Itaguaí' => ['itaguaí', 'itaguai', 'ITAGUAÍ'],
            'Itaocara' => ['itaocara', 'ITAOCARA'],
            'Itaperuna' => ['itaperuna', 'ITAPERUNA'],
            'Itatiaia' => ['itatiaia', 'ITATIAIA'],
            'Japeri' => ['japeri', 'JAPERI'],
            'Laje do Muriaé' => ['laje do muriaé', 'laje do muriae'],
            'Macaé' => ['Macaé RJ', 'Macaé rj', 'macaé', 'macae'],
            'Macuco' => ['macuco', 'MACUCO'],
            'Magé' => ['magé', 'mage', 'Magé-RJ', 'Magé-Rj', 'Magé-rj', 'Mage-RJ', 'Mage-Rj', 'Mage-rj', 'Magé Rj', 'Magé rj', 'Mage RJ', 'Mage Rj', 'Mage rj'],
            'Mangaratiba' => ['mangaratiba', 'MANGARATIBA'],
            'Maricá' => ['maricá', 'marica'],
            'Mendes' => ['mendes', 'MENDES'],
            'Mesquita' => ['mesquita', 'MESQUITA'],
            'Miguel Pereira' => ['miguel pereira'],
            'Miracema' => ['miracema'],
            'Natividade' => ['natividade'],
            'Nilópolis' => ['nilópolis', 'nilopolis'],
            'Niterói' => ['niterói', 'niteroi'],
            'Nova Friburgo' => ['Nova friburgo', 'nova friburgo'],
            'Nova Iguaçu' => ['nova iguaçu', 'nova iguacu', 'Nova iguacu', 'Nova iguaçu'],
            'Paracambi' => ['paracambi'],
            'Paraíba do Sul' => ['paraíba do sul', 'paraiba do sul'],
            'Paraty' => ['paraty'],
            'Paty do Alferes' => ['paty do alferes'],
            'Petrópolis' => ['petrópolis', 'petropolis', 'Petropolis', 'Petrópolis RJ', 'Petrópolis Rj'],
            'Pinheiral' => ['pinheiral'],
            'Piraí' => ['piraí', 'pirai'],
            'Porciúncula' => ['porciúncula', 'porcioncula'],
            'Porto Real' => ['porto real'],
            'Quatis' => ['quatis'],
            'Queimados' => ['queimados'],
            'Quissamã' => ['quissamã', 'Quissama', 'quissama'],
            'Resende' => ['resende'],
            'Rio Bonito' => ['Rio bonito', 'rio bonito'],
            'Rio Claro' => ['Rio claro', 'rio claro'],
            'Rio das Flores' => ['Rio das flores', 'rio das flores'],
            'Rio das Ostras' => ['Rio das ostras', 'rio das ostras'],
            'Rio de Janeiro' => ['Rio de janeiro', 'rio de janeiro', 'RIO DE JANEIRO', 'Río de Janeiro', 'Río de janeiro', 'RJ', 'rj', 'Rj', 'Campo Grande. RJ', 'rio de janeiro, campo grande', 'Campo Grande RJ', 'Campo Grande Rio de Janeiro'],
            'Santa Maria Madalena' => ['Santa maria madalena', 'Santa Maria madalena', 'santa maria madalena'],
            'Santo Antônio de Pádua' => ['santo antônio de pádua', 'santo antonio de padua'],
            'São Fidélis' => ['são fidélis', 'sao fidelis'],
            'São Francisco de Itabapoana' => ['são francisco de itabapoana', 'sao francisco de itabapoana'],
            'São Gonçalo' => ['São gonçalo', 'São goncalo', 'São Goncalo', 'são gonçalo', 'sao goncalo'],
            'São João da Barra' => ['São joão da barra', 'São João da barra', 'Sao Joao da Barra', 'são joão da barra', 'sao joao da barra'],
            'São João de Meriti' => ['são joão de meriti', 'sao joao de meriti'],
            'São José de Ubá' => ['são josé de ubá', 'sao jose de uba'],
            'São José do Vale do Rio Preto' => ['são josé do vale do rio preto', 'sao jose do vale do rio preto'],
            'São Pedro da Aldeia' => ['são pedro da aldeia', 'sao pedro da aldeia'],
            'São Sebastião do Alto' => ['são sebastião do alto', 'sao sebastiao do alto'],
            'Sapucaia' => ['sapucaia'],
            'Saquarema' => ['saquarema'],
            'Seropédica' => ['seropédica', 'seropedica'],
            'Silva Jardim' => ['silva jardim'],
            'Sumidouro' => ['sumidouro'],
            'Tanguá' => ['Tangua', 'tanguá', 'tangua'],
            'Teresópolis' => ['Teresópolis rj', 'Teresópolis RJ', 'teresópolis', 'teresopolis'],
            'Trajano de Moraes' => ['trajano de moraes'],
            'Três Rios' => ['três rios', 'tres rios'],
            'Valença' => ['Valença RJ', 'Valença Rj', 'Valença rj', 'valença', 'valenca', 'Valença-RJ', 'Valença-Rj', 'Valença - RJ', 'Valenca Rj', 'Valenca rj', 'Santa Isabel do Rio Preto - Valença', 'Valença E d R', 'Valença Estado do Rio .', 'Valença estado do Rio', 'Valençarj', 'Valença/RJ', 'Valença/Rj', 'Valença/rj'],
            'Varre-Sai' => ['varre-sai', 'varre sai'],
            'Vassouras' => ['vassouras'],
            'Volta Redonda' => ['volta redonda'],
            'São Paulo' => ['São paulo', 'sao paulo', 'sp', 'Sao Paulo', 'SP', 'Sp', 's.paulo'],
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
