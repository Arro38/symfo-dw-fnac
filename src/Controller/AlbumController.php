<?php

namespace App\Controller;

use App\Entity\Album;
use App\Form\AlbumType;
use App\Repository\AlbumRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/album')]
class AlbumController extends AbstractController
{
    #[Route('/', name: 'app_album_index', methods: ['GET'])]
    public function index(AlbumRepository $albumRepository): Response
    {
        return $this->render('album/index.html.twig', [
            'albums' => $albumRepository->findAll(),
        ]);
    }
    public function uploadPictureFile($picture, $slugger)
    {
        $originalFilename = pathinfo($picture->getClientOriginalName(), PATHINFO_FILENAME);
        // this is needed to safely include the file name as part of the URL
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $picture->guessExtension();

        // Move the file to the directory where brochures are stored
        try {
            $picture->move(
                $this->getParameter('album_picture_directory'),
                $newFilename
            );
            return $newFilename;
        } catch (FileException $e) {
            // ... handle exception if something happens during file upload
            echo $e->getMessage();
        }
    }

    #[Route('/new', name: 'app_album_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $album = new Album();
        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $picture = $form->get('picture')->getData();
            if ($picture) {

                $newFilename = $this->uploadPictureFile($picture, $slugger);
                // updates the 'brochureFilename' property to store the PDF file name
                // instead of its contents
                $album->setPictureFilename($newFilename);
            }
            $entityManager->persist($album);
            $entityManager->flush();

            return $this->redirectToRoute('app_album_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('album/new.html.twig', [
            'album' => $album,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_album_show', methods: ['GET'])]
    public function show(Album $album): Response
    {
        return $this->render('album/show.html.twig', [
            'album' => $album,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_album_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Album $album, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $picture = $form->get('picture')->getData();
            if ($picture) {

                $newFilename = $this->uploadPictureFile($picture, $slugger);
                // updates the 'brochureFilename' property to store the PDF file name
                // instead of its contents
                $album->setPictureFilename($newFilename);
            }
            $entityManager->flush();

            return $this->redirectToRoute('app_album_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('album/edit.html.twig', [
            'album' => $album,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_album_delete', methods: ['POST'])]
    public function delete(Request $request, Album $album, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if ($this->isCsrfTokenValid('delete' . $album->getId(), $request->request->get('_token'))) {
            $entityManager->remove($album);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_album_index', [], Response::HTTP_SEE_OTHER);
    }
}
