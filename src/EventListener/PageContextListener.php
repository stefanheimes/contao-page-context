<?php

declare(strict_types=1);

namespace Netzmacht\Contao\PageContext\EventListener;

use Contao\CoreBundle\Exception\AccessDeniedException;
use Netzmacht\Contao\PageContext\Request\PageContextFactory;
use Netzmacht\Contao\PageContext\Request\PageContextInitializer;
use Netzmacht\Contao\PageContext\Request\PageIdDeterminator;
use Netzmacht\Contao\PageContext\Security\PageContextVoter;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use function sprintf;

final class PageContextListener
{
    /**
     * Page id determinator.
     *
     * @var PageIdDeterminator
     */
    private $pageIdDeterminator;

    /**
     * Page context factory.
     *
     * @var PageContextFactory
     */
    private $contextFactory;

    /**
     * Page context initializer.
     *
     * @var PageContextInitializer
     */
    private $initializer;

    /**
     * Authorization checker.
     *
     * @var AuthorizationCheckerInterface
     */
    private $authorizationChecker;

    /**
     * @param PageIdDeterminator            $pagIdDeterminator    Page id determinator.
     * @param PageContextFactory            $contextFactory       Page context factory.
     * @param PageContextInitializer        $initializer          Page context initializer.
     * @param AuthorizationCheckerInterface $authorizationChecker Authorization checker.
     */
    public function __construct(
        PageIdDeterminator $pagIdDeterminator,
        PageContextFactory $contextFactory,
        PageContextInitializer $initializer,
        AuthorizationCheckerInterface $authorizationChecker
    ) {
        $this->pageIdDeterminator   = $pagIdDeterminator;
        $this->contextFactory       = $contextFactory;
        $this->initializer          = $initializer;
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * {@inheritdoc}
     *
     * @throws AccessDeniedException If user is not granted to access page context.
     */
    public function __invoke(KernelEvent $event): void
    {
        $request = $event->getRequest();

        if (! $this->pageIdDeterminator->match($request)) {
            return;
        }

        $pageId  = $this->pageIdDeterminator->determinate($request);
        $context = ($this->contextFactory)($pageId);

        if (! $this->authorizationChecker->isGranted(PageContextVoter::VIEW, $context)) {
            throw new AccessDeniedException(
                sprintf('Access denied to page context ID "%s" for given URI "%s"', $pageId, $request->getUri())
            );
        }

        $request->attributes->set('_page_context', $context);
        $this->initializer->initialize($context, $request);
    }
}
