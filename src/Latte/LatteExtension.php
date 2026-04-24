<?php declare(strict_types = 1);

namespace SkadminUtils\ImageStorage\Latte;

use Latte\Compiler\Node;
use Latte\Compiler\Nodes\AuxiliaryNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Extension;

class LatteExtension extends Extension
{

	/**
	 * @return array<mixed>
	 */
	public function getTags(): array
	{
		return [
			'img' => [$this, 'tagImg'],
			'imgAbs' => [$this, 'tagImgAbs'],
			'imgSrc' => [$this, 'tagImgSrc'],
			'imgSrcAbs' => [$this, 'tagImgSrcAbs'],
			'imgsrc' => [$this, 'tagImgSrc'],
			'imgsrcAbs' => [$this, 'tagImgSrcAbs'],
			'n:img' => [$this, 'attrImg'],
			'n:imgAbs' => [$this, 'attrImgAbs'],
			'imgLink' => [$this, 'linkImg'],
			'imgLinkAbs' => [$this, 'linkImgAbs'],
		];
	}

	public function tagImg(Tag $tag): Node
	{
		$tag->parser->stream->tryConsume(',');
		$args = $tag->parser->parseArguments();

		return new AuxiliaryNode(
			fn (PrintContext $context) => $context->format('echo "<img" . $imageStorage->createImgAttributes(%node, $basePath) . ">";', $args)
		);
	}

	public function tagImgAbs(Tag $tag): Node
	{
		$args = $tag->parser->parseArguments();

		return new AuxiliaryNode(
			fn (PrintContext $context) => $context->format('echo "<img" . $imageStorage->createImgAttributes(%node, $baseUrl) . ">";', $args)
		);
	}

	public function tagImgSrc(Tag $tag): Node
	{
		$args = $tag->parser->parseArguments();

		return new AuxiliaryNode(
			fn (PrintContext $context) => $context->format('echo $imageStorage->createImgAttributes(%node, $basePath);', $args)
		);
	}

	public function tagImgSrcAbs(Tag $tag): Node
	{
		$args = $tag->parser->parseArguments();

		return new AuxiliaryNode(
			fn (PrintContext $context) => $context->format('echo $imageStorage->createImgAttributes(%node, $baseUrl);', $args)
		);
	}

	public function attrImg(Tag $tag): Node
	{
		$args = $tag->parser->parseArguments();

		return new AuxiliaryNode(
			fn (PrintContext $context) => $context->format('echo $imageStorage->createImgAttributes(%node, $basePath);', $args)
		);
	}

	public function attrImgAbs(Tag $tag): Node
	{
		$args = $tag->parser->parseArguments();

		return new AuxiliaryNode(
			fn (PrintContext $context) => $context->format('echo $imageStorage->createImgAttributes(%node, $baseUrl);', $args)
		);
	}

	public function linkImg(Tag $tag): Node
	{
		$args = $tag->parser->parseArguments();

		return new AuxiliaryNode(
			fn (PrintContext $context) => $context->format('$_img = $imageStorage->fromIdentifier(%node); echo $basePath . "/" . $_img->createLink();', $args)
		);
	}

	public function linkImgAbs(Tag $tag): Node
	{
		$args = $tag->parser->parseArguments();

		return new AuxiliaryNode(
			fn (PrintContext $context) => $context->format('$_img = $imageStorage->fromIdentifier(%node); echo $baseUrl . "/" . $_img->createLink();', $args)
		);
	}

}
