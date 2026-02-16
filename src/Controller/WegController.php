<?php

namespace App\Controller;

use App\Entity\KategorisierungsTyp;
use App\Entity\Kostenkonto;
use App\Entity\Umlageschluessel;
use App\Entity\User;
use App\Entity\Weg;
use App\Entity\WegEinheit;
use App\Repository\KostenkontoRepository;
use App\Repository\UmlageschluesselRepository;
use App\Repository\UserRepository;
use App\Repository\WegEinheitRepository;
use App\Repository\WegRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class WegController extends AbstractController
{
    #[Route('/weg', name: 'app_weg_index')]
    public function index(
        WegRepository $wegRepository,
        WegEinheitRepository $wegEinheitRepository,
        UmlageschluesselRepository $umlageschluesselRepository,
        KostenkontoRepository $kostenkontoRepository,
        \App\Repository\ZahlungskategorieRepository $zahlungskategorieRepository,
        UserRepository $userRepository,
    ): Response {
        // Get Umlageschlüssel and sort in HGA display order (same as HgaService)
        $umlageschluessel = $umlageschluesselRepository->findAll();
        $hgaOrder = ['01*', '02*', '03*', '04*', '05*', '06*', '07*'];
        usort($umlageschluessel, static function ($a, $b) use ($hgaOrder) {
            $posA = array_search($a->getSchluessel(), $hgaOrder, true);
            $posB = array_search($b->getSchluessel(), $hgaOrder, true);

            // If not found in order array, put at end
            if (false === $posA) {
                $posA = 999;
            }
            if (false === $posB) {
                $posB = 999;
            }

            return $posA <=> $posB;
        });

        $templateData = [
            'wegs' => $wegRepository->findAll(),
            'wegEinheiten' => $wegEinheitRepository->findAll(),
            'umlageschluessel' => $umlageschluessel,
            'kostenkontos' => $kostenkontoRepository->findBy([], ['nummer' => 'ASC']),
            'kategorisierungsTypen' => KategorisierungsTyp::cases(),
            'zahlungskategorien' => $zahlungskategorieRepository->findBy([], ['name' => 'ASC']),
        ];

        // Only load user data for SUPER_ADMIN
        if ($this->isGranted(User::ROLE_SUPER_ADMIN)) {
            $templateData['users'] = $userRepository->findBy([], ['firstName' => 'ASC', 'lastName' => 'ASC']);
            $templateData['availableRoles'] = User::getAvailableRoles();
        }

        return $this->render('weg/index.html.twig', $templateData);
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

    #[Route('/weg/user/new', name: 'app_weg_user_new', methods: ['POST'])]
    #[IsGranted(User::ROLE_SUPER_ADMIN)]
    public function newUser(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $email = trim($request->request->get('email', ''));
        $firstName = trim($request->request->get('firstName', ''));
        $lastName = trim($request->request->get('lastName', ''));
        $password = $request->request->get('password', '');
        $roles = $request->request->all('roles');

        if (!$email || !$firstName || !$lastName || !$password) {
            $this->addFlash('error', 'Alle Pflichtfelder müssen ausgefüllt sein.');

            return $this->redirectToRoute('app_weg_index', ['tab' => 'benutzer']);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setIsActive(true);
        $user->setRoles($roles);

        $hashedPassword = $passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $entityManager->persist($user);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Benutzer "%s" wurde erstellt.', $user->getFullName()));

        return $this->redirectToRoute('app_weg_index', ['tab' => 'benutzer']);
    }

    #[Route('/weg/user/{id}/edit', name: 'app_weg_user_edit', methods: ['POST'])]
    #[IsGranted(User::ROLE_SUPER_ADMIN)]
    public function editUser(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $email = trim($request->request->get('email', ''));
        $firstName = trim($request->request->get('firstName', ''));
        $lastName = trim($request->request->get('lastName', ''));
        $password = $request->request->get('password', '');
        $roles = $request->request->all('roles');

        if ($email) {
            $user->setEmail($email);
        }
        if ($firstName) {
            $user->setFirstName($firstName);
        }
        if ($lastName) {
            $user->setLastName($lastName);
        }
        if ($password) {
            $hashedPassword = $passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
        }
        $user->setRoles($roles);
        $user->setUpdatedAt(new \DateTime());

        $entityManager->flush();

        $this->addFlash('success', sprintf('Benutzer "%s" wurde aktualisiert.', $user->getFullName()));

        return $this->redirectToRoute('app_weg_index', ['tab' => 'benutzer']);
    }

    #[Route('/weg/user/{id}/toggle', name: 'app_weg_user_toggle', methods: ['POST'])]
    #[IsGranted(User::ROLE_SUPER_ADMIN)]
    public function toggleUserActive(
        User $user,
        EntityManagerInterface $entityManager,
    ): Response {
        $user->setIsActive(!$user->isActive());
        $user->setUpdatedAt(new \DateTime());
        $entityManager->flush();

        $status = $user->isActive() ? 'aktiviert' : 'deaktiviert';
        $this->addFlash('success', sprintf('Benutzer "%s" wurde %s.', $user->getFullName(), $status));

        return $this->redirectToRoute('app_weg_index', ['tab' => 'benutzer']);
    }
}
