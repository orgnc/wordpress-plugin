import { useBlockProps } from '@wordpress/block-editor';

import ProductCard from './ProductCard';
import { AttributesType } from './propTypes';

const Save = ({ attributes }) => {
  return (
    // eslint-disable-next-line react/jsx-props-no-spreading
    <div {...useBlockProps.save()}>
      {attributes?.productGuid
        && (
        <ProductCard
          bannerText={attributes.bannerText}
          displayDescription={attributes.displayDescription}
          displayImage={attributes.displayImage}
          productGuid={attributes.productGuid}
          description={attributes.description}
        />
        )}
    </div>
  );
};

Save.propTypes = {
  attributes: AttributesType.isRequired,
};

export default Save;
