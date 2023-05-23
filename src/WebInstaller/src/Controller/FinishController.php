<?php
declare(strict_types=1);

namespace App\Controller;

use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Package('core')]
class FinishController extends AbstractController
{
    #[Route('/finish', name: 'finish', defaults: ['step' => 3])]
    public function default(Request $request): Response
    {
        // @codeCoverageIgnoreStart
        if ($request->getMethod() === Request::METHOD_POST) {
            $self = $_SERVER['SCRIPT_FILENAME'];
            \assert(\is_string($self));

            $redirectUrl = $request->getBasePath() . '/admin';

            // Below this line call only php native functions as we deleted our own files already
            unlink($self);

            header('Content-Type: text/html; charset=utf-8');
            echo '<script>window.location.href = "' . $redirectUrl . '" </script>';
            exit;
        }
        // @codeCoverageIgnoreEnd

        return $this->render('finish.html.twig');
    }
}
