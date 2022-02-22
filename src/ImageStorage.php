<?php declare(strict_types=1);

namespace SkadminUtils\ImageStorage;

use SkadminUtils\ImageStorage\Exception\ImageExtensionException;
use SkadminUtils\ImageStorage\Exception\ImageResizeException;
use SkadminUtils\ImageStorage\Exception\ImageStorageException;
use DirectoryIterator;
use Nette\Http\FileUpload;
use Nette\SmartObject;
use Nette\Utils\Image as NetteImage;
use Nette\Utils\Strings;
use Nette\Utils\UnknownImageFileException;
use Tracy\Debugger;

class ImageStorage
{

	use SmartObject;

	/** @var string */
	private $data_path;

	/** @var string */
	private $data_dir;

	/** @var string */
	private $data_path_cache;

	/** @var string */
	private $data_dir_cache;

	/** @var callable(string): string */
	private $algorithm_file;

	/** @var callable(string): string */
	private $algorithm_content;

	/** @var int */
	private $quality;

	/** @var string */
	private $default_transform;

	/** @var string */
	private $noimage_identifier;

	/** @var bool */
	private $friendly_url;

	/** @var int */
	private $mask = 0775;

	/** @var int[] */
	private $_image_flags = [
		'fit' => 0,
		'fill' => 4,
		'exact' => 8,
		'stretch' => 2,
		'shrink_only' => 1,
	];

	/**
	 * @param callable(string): string $algorithm_file
	 * @param callable(string): string $algorithm_content
	 */
	public function __construct(
		string   $data_path,
		string   $data_dir,
		string   $data_path_cache,
		string   $data_dir_cache,
		callable $algorithm_file,
		callable $algorithm_content,
		int      $quality,
		string   $default_transform,
		string   $noimage_identifier,
		bool     $friendly_url
	)
	{
		$this->data_path = $data_path;
		$this->data_dir = $data_dir;
		$this->data_path_cache = $data_path_cache ? $data_path_cache : sprintf('%s_cache', $data_path);
		$this->data_dir_cache = $data_dir_cache ? $data_dir_cache : sprintf('%s_cache', $data_dir);
		$this->algorithm_file = $algorithm_file;
		$this->algorithm_content = $algorithm_content;
		$this->quality = $quality;
		$this->default_transform = $default_transform;
		$this->noimage_identifier = $noimage_identifier;
		$this->friendly_url = $friendly_url;
	}

	/**
	 * @param mixed $arg
	 */
	public function delete($arg, bool $onlyChangedImages = false): void
	{
		$script = is_object($arg) && $arg instanceof Image ? ImageNameScript::fromIdentifier($arg->identifier) : ImageNameScript::fromName($arg);

		$fncDelete = function (string $pattern, string $dir, string $file): void {
			if (!file_exists($dir)) {
				return;
			}

			foreach (new DirectoryIterator($dir) as $file_info) {
				if (!preg_match($pattern, $file_info->getFilename()) || !(!$onlyChangedImages || $file !== $file_info->getFilename())) {
					continue;
				}

				unlink($file_info->getPathname());
			}
		};

		// CACHE
		$patternCache = preg_replace('/__file__/', $script->name, ImageNameScript::PATTERN);
		$dirCache = implode('/', [$this->data_path_cache, $script->namespace, $script->prefix]);
		$fileCache = $script->name . '.' . $script->extension;

		$fncDelete($patternCache, $dirCache, $fileCache);

		// ORIG
		$patternOrig = preg_replace('/__file__/', $script->name, ImageNameScript::PATTERN);
		$dirOrig = implode('/', [$this->data_path, $script->namespace, $script->prefix]);
		$fileOrig = $script->name . '.' . $script->extension;

		$fncDelete($patternOrig, $dirOrig, $fileOrig);
	}

	public function saveUpload(FileUpload $upload, string $namespace, ?string $checksum = null): Image
	{
		if (!$checksum) {
			$checksum = call_user_func_array($this->algorithm_file, [$upload->getTemporaryFile()]);
		}

		[$path, $identifier] = $this->getSavePath(
			self::fixName($upload->getUntrustedName()),
			$namespace,
			$checksum
		);

		$upload->move($path);

		return new Image($this->friendly_url, $this->data_dir, $this->data_path, $identifier, [
			'sha' => $checksum,
			'name' => self::fixName($upload->getUntrustedName()),
		]);
	}

	private static function fixName(string $name): string
	{
		return Strings::webalize($name, '._');
	}


	/**
	 * @param mixed $content
	 */
	public function saveContent($content, string $name, string $namespace, ?string $checksum = null): Image
	{
		if (!$checksum) {
			$checksum = call_user_func_array($this->algorithm_content, [$content]);
		}

		[$path, $identifier] = $this->getSavePath(
			self::fixName($name),
			$namespace,
			$checksum
		);

		file_put_contents($path, $content, LOCK_EX);

		return new Image($this->friendly_url, $this->data_dir, $this->data_path, $identifier, [
			'sha' => $checksum,
			'name' => self::fixName($name),
		]);
	}

	/**
	 * @param mixed $args
	 */
	public function fromIdentifier($args): Image
	{
		if (!is_array($args)) {
			$args = [$args];
		}

		$identifierOrig = $args[0];

		$isNoImage = false;

		if (count($args) === 1) {
			if (!file_exists(implode('/', [$this->data_path, $identifierOrig])) || !$identifierOrig) {
				return $this->getNoImage(true);
			}

			return new Image($this->friendly_url, $this->data_dir, $this->data_path, $identifierOrig);
		}

		preg_match('/(\d+)?x(\d+)?(crop(\d+)x(\d+)x(\d+)x(\d+))?/', $args[1], $matches);
		$size = [(int)$matches[1], (int)$matches[2]];
		$crop = [];

		if (!$size[0] || !$size[1]) {
			throw new ImageResizeException('Error resizing image. You have to provide both width and height.');
		}

		if (count($matches) === 8) {
			$crop = [(int)$matches[4], (int)$matches[5], (int)$matches[6], (int)$matches[7]];
		}

		$flag = $args[2] ?? $this->default_transform;
		$quality = $args[3] ?? $this->quality;

		if (!$identifierOrig) {
			$isNoImage = false;
			[$script, $file] = $this->getNoImage(false);
		} else {
			$script = ImageNameScript::fromIdentifier($identifierOrig);

			$file = implode('/', [$this->data_path, $script->original]);

			if (!file_exists($file)) {
				$isNoImage = true;
				[$script, $file] = $this->getNoImage(false);
			}
		}

		$script->setSize($size);
		$script->setCrop($crop);
		$script->setFlag($flag);
		$script->setQuality($quality);

		$identifier = $script->getIdentifier();

		$newPathCacheBase = implode('/', [$this->data_path_cache, $identifier]);
		$newPathCache = sprintf('%s.webp', $newPathCacheBase);
		if (!file_exists($newPathCacheBase) && !file_exists($newPathCache)) {
			if (!file_exists($file)) {
				return new Image(false, '#', '#', 'Can not find image');
			}

			try {
				$_image = NetteImage::fromFile($file);
			} catch (UnknownImageFileException $e) {
				return new Image(false, '#', '#', 'Unknown type of file');
			}

			if ($script->hasCrop() && !$isNoImage) {
				call_user_func_array([$_image, 'crop'], $script->crop);
			}

			if (strpos($flag, '+') !== false) {
				$bits = 0;

				foreach (explode('+', $flag) as $f) {
					$bits = $this->_image_flags[$f] | $bits;
				}

				$flag = $bits;
			} else {
				$flag = $this->_image_flags[$flag];
			}

			$_image->resize($size[0], $size[1], $flag);

			if (!file_exists($newPathCache)) {
				$dirNameCache = dirname($newPathCache);

				if (!file_exists($dirNameCache)) {
					@mkdir($dirNameCache, $this->mask, true); // Directory may exist
				}
			}

			try {
				$_image->sharpen()->save($newPathCacheBase, $quality);
				imagewebp($_image->getImageResource(), $newPathCache, $quality);
				unlink($newPathCacheBase);
			} catch (\Error $e) {
				// notsupport webp
			} catch (\Exception $e) {
				return new Image($this->friendly_url, $this->data_dir, $this->data_path, $identifierOrig);
			}

		}

		if (file_exists($newPathCache)) {
			return new Image($this->friendly_url, $this->data_dir_cache, $this->data_path_cache, sprintf('%s.webp', $identifier), ['script' => $script]);
		}

		return new Image($this->friendly_url, $this->data_dir_cache, $this->data_path_cache, $identifier, ['script' => $script]);
	}

	/**
	 * @return Image|mixed[]
	 * @phpstan-return Image|array{ImageNameScript, string}
	 * @throws ImageStorageException
	 */
	public function getNoImage(bool $return_image = false)
	{
		$script = ImageNameScript::fromIdentifier($this->noimage_identifier);
		$file = implode('/', [$this->data_path, $script->original]);

		if (!file_exists($file)) {
			$identifier = $this->noimage_identifier;
			$new_path = sprintf('%s/%s', $this->data_path, $identifier);

			if (!file_exists($new_path)) {
				$dirName = dirname($new_path);

				if (!file_exists($dirName)) {
					mkdir($dirName, 0777, true);
				}

				if (!file_exists($dirName) || !is_writable($dirName)) {
					throw new ImageStorageException('Could not create default no_image.png. ' . $dirName . ' does not exist or is not writable.');
				}

				$data = base64_decode(require __DIR__ . '/NoImageSource.php');
				$_image = NetteImage::fromString($data);
				$_image->save($new_path, $script->quality ?: $this->quality);
			}

			if ($return_image) {
				return new Image($this->friendly_url, $this->data_dir, $this->data_path, $identifier);
			}

			$script = ImageNameScript::fromIdentifier($identifier);

			return [$script, $new_path];
		}

		if ($return_image) {
			return new Image($this->friendly_url, $this->data_dir, $this->data_path, $this->noimage_identifier);
		}

		return [$script, $file];
	}


	/**
	 * @return string[]
	 * @throws ImageExtensionException
	 */
	private function getSavePath(string $name, string $namespace, string $checksum): array
	{
		$prefix = substr($checksum, 0, 2);
		$dir = implode('/', [$this->data_path, $namespace, $prefix]);

		@mkdir($dir, $this->mask, true); // Directory may exist

		preg_match('/(.*)(\.[^\.]*)/', $name, $matches);

		if (!$matches[2]) {
			throw new ImageExtensionException(sprintf('Error defining image extension (%s)', $name));
		}

		$name = $matches[1];
		$extension = $matches[2];

		while (file_exists($path = $dir . '/' . $name . $extension)) {
			$name = (!isset($i) && ($i = 2)) ? $name . '.' . $i : substr($name, 0, -(2 + (int)floor(log($i - 1, 10)))) . '.' . $i;
			$i++;
		}

		$identifier = implode('/', [$namespace, $prefix, $name . $extension]);

		return [$path, $identifier];
	}

	public function setFriendlyUrl(bool $friendly_url = true): void
	{
		$this->friendly_url = $friendly_url;
	}

}
