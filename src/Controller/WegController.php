<?php

namespace App\Controller;

use App\Entity\KategorisierungsTyp;
use App\Entity\Kostenkonto;
use App\Entity\Umlageschluessel;
use App\Entity\Weg;
use App\Entity\WegEinheit;
use App\Repository\KostenkontoRepository;
use App\Repository\UmlageschluesselRepository;
use App\Repository\WegEinheitRepository;
use App\Repository\WegRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WegController extends AbstractController
{
    #[Route('/weg', name: 'app_weg_index')]
    public function index(
        WegRepository $wegRepository,
        WegEinheitRepository $wegEinheitRepository,
        UmlageschluesselRepository $umlageschluesselRepository,
        KostenkontoRepository $kostenkontoRepository,
        \App\Repository\ZahlungskategorieRepository $zahlungskategorieRepository,
    ): Response {
        return $this->render('weg/index.html.twig', [
            'wegs' => $wegRepository->findAll(),
            'wegEinheiten' => $wegEinheitRepository->findAll(),
            'umlageschluessel' => $umlageschluesselRepository->findAll(),
            'kostenkontos' => $kostenkontoRepository->findBy([], ['nummer' => 'ASC']),
            'kategorisierungsTypen' => KategorisierungsTyp::cases(),
            'zahlungskategorien' => $zahlungskategorieRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/weg/umlageschluessel/{id}/edit', name: 'app_weg_umlageschluessel_edit', methods: ['POST'])]
    public function editUmlageschluessel(
        Umlageschluessel $umlageschluessel,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $bezeichnung = $request->request->get('bezeichnung');
        $beschreibung = $request->request->get('beschreibung');

        if ($bezeichnung) {
            $umlageschluessel->setBezeichnung($bezeichnung);
        }
        if ($beschreibung) {
            $umlageschluessel->setBeschreibung($beschreibung);
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_weg_index', ['tab' => 'umlageschluessel']);
    }

    #[Route('/weg/{id}/edit', name: 'app_weg_edit', methods: ['POST'])]
    public function editWeg(
        Weg $weg,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $bezeichnung = $request->request->get('bezeichnung');
        $adresse = $request->request->get('adresse');

        if ($bezeichnung) {
            $weg->setBezeichnung($bezeichnung);
        }
        if ($adresse) {
            $weg->setAdresse($adresse);
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_weg_index', ['tab' => 'weg']);
    }

    #[Route('/weg/einheit/{id}/edit', name: 'app_weg_einheit_edit', methods: ['POST'])]
    public function editWegEinheit(
        WegEinheit $wegEinheit,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $nummer = $request->request->get('nummer');
        $bezeichnung = $request->request->get('bezeichnung');
        $miteigentuemer = $request->request->get('miteigentuemer');
        $miteigentumsanteile = $request->request->get('miteigentumsanteile');
        $stimme = $request->request->get('stimme');
        $adresse = $request->request->get('adresse');
        $telefon = $request->request->get('telefon');
        $hauptwohneinheit = $request->request->get('hauptwohneinheit');

        if ($nummer) {
            $wegEinheit->setNummer($nummer);
        }
        if ($bezeichnung) {
            $wegEinheit->setBezeichnung($bezeichnung);
        }
        if ($miteigentuemer) {
            $wegEinheit->setMiteigentuemer($miteigentuemer);
        }
        if ($miteigentumsanteile) {
            $wegEinheit->setMiteigentumsanteile($miteigentumsanteile);
        }
        if ($stimme) {
            $wegEinheit->setStimme($stimme);
        }
        if ($adresse) {
            $wegEinheit->setAdresse($adresse);
        }
        if ($telefon) {
            $wegEinheit->setTelefon($telefon);
        }
        if (null !== $hauptwohneinheit) {
            $wegEinheit->setHauptwohneinheit((bool) $hauptwohneinheit);
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_weg_index', ['tab' => 'einheiten']);
    }

    #[Route('/weg/kostenkonto/{id}/edit', name: 'app_weg_kostenkonto_edit', methods: ['POST'])]
    public function editKostenkonto(
        Kostenkonto $kostenkonto,
        Request $request,
        EntityManagerInterface $entityManager,
        UmlageschluesselRepository $umlageschluesselRepository,
    ): Response {
        $nummer = $request->request->get('nummer');
        $bezeichnung = $request->request->get('bezeichnung');
        $kategorisierungsTyp = $request->request->get('kategorisierungsTyp');
        $umlageschluesselId = $request->request->get('umlageschluessel');
        $isActive = $request->request->get('isActive');
        $taxDeductible = $request->request->get('taxDeductible');

        if ($nummer) {
            $kostenkonto->setNummer($nummer);
        }
        if ($bezeichnung) {
            $kostenkonto->setBezeichnung($bezeichnung);
        }
        if ($kategorisierungsTyp && \is_string($kategorisierungsTyp)) {
            $kostenkonto->setKategorisierungsTyp(KategorisierungsTyp::from($kategorisierungsTyp));
        }
        if ($umlageschluesselId) {
            $umlageschluessel = $umlageschluesselRepository->find($umlageschluesselId);
            $kostenkonto->setUmlageschluessel($umlageschluessel);
        } elseif ('' === $umlageschluesselId) {
            $kostenkonto->setUmlageschluessel(null);
        }
        if (null !== $isActive) {
            $kostenkonto->setIsActive((bool) $isActive);
        }
        if (null !== $taxDeductible) {
            $kostenkonto->setTaxDeductible((bool) $taxDeductible);
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_weg_index', ['tab' => 'kostenkonto']);
    }
}
